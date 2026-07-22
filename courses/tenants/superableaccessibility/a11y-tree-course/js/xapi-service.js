/**
 * xAPI Service for Accessible by Design Course
 * Handles tracking of user interactions using vanilla JavaScript and fetch.
 */

// Clean query parameters to handle external launch wrappers appending parameters with "?" instead of "&"
let searchString = window.location.search;
if (searchString.length > 1) {
    searchString = '?' + searchString.substring(1).replace(/\?/g, '&');
}
const urlParams = new URLSearchParams(searchString);

// Extract LRS launch parameters
const lrsEndpoint = urlParams.get('endpoint');
const authHeader = urlParams.get('auth');
const actorParam = urlParams.get('actor');
const activityIdParam = urlParams.get('activityId') || 
                        urlParams.get('activity_id') || 
                        urlParams.get('activity-id') || 
                        urlParams.get('activity') || 
                        urlParams.get('id');

const currentCourseIdVal = urlParams.get('course_id') || "a11y-tree-course";
const fallbackActivityId = `${window.location.origin}${window.location.pathname}?course_id=${currentCourseIdVal}`;
const courseId = activityIdParam || fallbackActivityId;

const registrationParam = urlParams.get('registration') || 
                          urlParams.get('registration_id') || 
                          urlParams.get('registrationId');
const groupingParam = urlParams.get('grouping') || 
                      urlParams.get('grouping_id') || 
                      urlParams.get('groupingId');

// Default actor fallback
let actor = {
    "objectType": "Agent",
    "name": "Local Tester",
    "mbox": "mailto:test@jacobwood.me"
};

if (actorParam) {
    try {
        actor = JSON.parse(actorParam);
    } catch (e) {
        console.error("xAPI: Failed to parse actor JSON:", e);
        if (typeof actorParam === 'string') {
            if (actorParam.includes('@')) {
                actor = {
                    "objectType": "Agent",
                    "mbox": actorParam.startsWith('mailto:') ? actorParam : 'mailto:' + actorParam,
                    "name": actorParam.split('@')[0]
                };
            } else {
                actor = {
                    "objectType": "Agent",
                    "name": actorParam,
                    "mbox": "mailto:guest@example.com"
                };
            }
        }
    }
} else if (window.LMS_CONTEXT && window.LMS_CONTEXT.userEmail) {
    actor = {
        "objectType": "Agent",
        "name": window.LMS_CONTEXT.userName || "LMS User",
        "mbox": window.LMS_CONTEXT.userEmail.startsWith('mailto:') ? window.LMS_CONTEXT.userEmail : "mailto:" + window.LMS_CONTEXT.userEmail
    };
}

// Override anonymous/guest actor from XCL if LMS user is logged in
const isInitialAnonymous = !actor || 
    (actor.name && (actor.name.includes("Anonymous") || actor.name.includes("Guest") || actor.name.includes("Not Found"))) ||
    (actor.mbox && (actor.mbox.includes("guest") || actor.mbox.includes("anonymous")));

if (isInitialAnonymous && window.LMS_CONTEXT && window.LMS_CONTEXT.userEmail && !window.LMS_CONTEXT.userEmail.includes("guest")) {
    actor = {
        "objectType": "Agent",
        "name": window.LMS_CONTEXT.userName || "LMS User",
        "mbox": window.LMS_CONTEXT.userEmail.startsWith('mailto:') ? window.LMS_CONTEXT.userEmail : "mailto:" + window.LMS_CONTEXT.userEmail
    };
}

/**
 * Sends an xAPI statement to the LRS using fetch.
 */
async function sendStatement(verb, object, result = null) {
    // Dynamically update/override anonymous actor if LMS context has a real user logged in
    const isAnonymousActor = !actor || 
        (actor.name && (actor.name.includes("Anonymous") || actor.name.includes("Guest") || actor.name.includes("Not Found"))) ||
        (actor.mbox && (actor.mbox.includes("guest") || actor.mbox.includes("anonymous"))) ||
        actor.name === "Local Tester";

    if (isAnonymousActor && window.LMS_CONTEXT && window.LMS_CONTEXT.userEmail && !window.LMS_CONTEXT.userEmail.includes("guest")) {
        actor = {
            "objectType": "Agent",
            "name": window.LMS_CONTEXT.userName || "LMS User",
            "mbox": window.LMS_CONTEXT.userEmail.startsWith('mailto:') ? window.LMS_CONTEXT.userEmail : "mailto:" + window.LMS_CONTEXT.userEmail
        };
    }

    const statement = {
        "actor": actor,
        "verb": verb,
        "object": object,
        "timestamp": new Date().toISOString()
    };

    if (registrationParam || groupingParam) {
        statement.context = {};
        if (registrationParam) {
            statement.context.registration = registrationParam;
        }
        if (groupingParam) {
            statement.context.contextActivities = {
                "grouping": [{ "id": groupingParam }]
            };
        }
    }

    if (result) {
        statement.result = result;
    }

    // Log to console if testing locally or missing configuration
    if (!lrsEndpoint || !authHeader) {
        console.log("%cxAPI Statement (Local Test):", "color: #007acc; font-weight: bold;", JSON.stringify(statement, null, 2));
        return;
    }

    let statementsUrl = lrsEndpoint;
    if (!statementsUrl.endsWith('/')) {
        statementsUrl += '/';
    }
    if (!statementsUrl.toLowerCase().endsWith('/statements') && !statementsUrl.toLowerCase().endsWith('/statements/')) {
        statementsUrl += 'statements';
    }

    const formattedAuth = /^Basic\s+/i.test(authHeader) ? authHeader : 'Basic ' + authHeader;

    try {
        const response = await fetch(statementsUrl, {
            method: "POST",
            headers: {
                "Authorization": formattedAuth,
                "Content-Type": "application/json",
                "X-Experience-API-Version": "1.0.3"
            },
            body: JSON.stringify(statement)
        });

        if (!response.ok) {
            throw new Error(`LRS responded with ${response.status}`);
        }
    } catch (error) {
        console.error("xAPI Statement Failed:", error);
    }
}

// Define course-specific helper methods to match old ADL helper architecture
const xapiService = {
    isInitialized: true, // Mark true immediately since we use dynamic config on load
    lrsConfigured: !!(lrsEndpoint && authHeader),
    debug: true,

    verbs: {
        initialized: { id: "http://adlnet.gov/expapi/verbs/initialized", display: { "en-US": "initialized" } },
        experienced: { id: "http://adlnet.gov/expapi/verbs/experienced", display: { "en-US": "experienced" } },
        passed: { id: "http://adlnet.gov/expapi/verbs/passed", display: { "en-US": "passed" } },
        failed: { id: "http://adlnet.gov/expapi/verbs/failed", display: { "en-US": "failed" } },
        completed: { id: "http://adlnet.gov/expapi/verbs/completed", display: { "en-US": "completed" } },
        answered: { id: "http://adlnet.gov/expapi/verbs/answered", display: { "en-US": "answered" } },
        interacted: { id: "http://adlnet.gov/expapi/verbs/interacted", display: { "en-US": "interacted" } }
    },

    sendStatement: function(verb, object, result = null) {
        return sendStatement(verb, object, result);
    },

    getCourseObject: function() {
        return {
            "id": courseId,
            "definition": {
                "name": { "en-US": "Accessible by Design: From DOM to a More Inclusive Web" },
                "description": { "en-US": "Main course container." },
                "type": "http://adlnet.gov/expapi/activities/course"
            }
        };
    },

    getPageObject: function(pageIndex, title) {
        return {
            "id": `${courseId}/module/${pageIndex}`,
            "definition": {
                "name": { "en-US": title },
                "description": { "en-US": `Course module ${pageIndex}` },
                "type": "http://adlnet.gov/expapi/activities/module"
            }
        };
    },

    getInteractionObject: function(pageIndex, interactionId, name, description) {
        return {
            "id": `${courseId}/module/${pageIndex}/${interactionId}`,
            "definition": {
                "name": { "en-US": name },
                "description": { "en-US": description || name },
                "type": "http://adlnet.gov/expapi/activities/interaction"
            }
        };
    },

    getQuestionObject: function(pageIndex, questionId, name, description) {
        return {
            "id": `${courseId}/module/${pageIndex}/question/${questionId}`,
            "definition": {
                "name": { "en-US": name },
                "description": { "en-US": description || name },
                "type": "http://adlnet.gov/expapi/activities/cmi.interaction"
            }
        };
    }
};

window.xapiService = xapiService;

// Bridge for window.xapi compatibility with player.php
window.xapi = {
    sendStatement: function(verb, object, result = null) {
        let mappedVerb = verb;
        if (verb && typeof verb.id === 'string') {
            const verbName = Object.keys(xapiService.verbs).find(
                key => xapiService.verbs[key].id === verb.id
            );
            if (verbName) {
                mappedVerb = xapiService.verbs[verbName];
            }
        }
        return sendStatement(mappedVerb, object, result);
    },
    verbs: {
        INITIALIZED: xapiService.verbs.initialized,
        EXPERIENCED: xapiService.verbs.experienced,
        PASSED: xapiService.verbs.passed,
        FAILED: xapiService.verbs.failed,
        COMPLETED: xapiService.verbs.completed,
        ANSWERED: xapiService.verbs.answered,
        INTERACTED: xapiService.verbs.interacted
    },
    get courseId() {
        return courseId;
    }
};
