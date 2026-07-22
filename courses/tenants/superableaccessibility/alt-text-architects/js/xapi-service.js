/**
 * xAPI Service for Complex Interactions Micro-Course
 * Handles tracking of user interactions using vanilla JavaScript.
 * Follows the 'Growth for ALL' philosophy and WCAG 2.2 standards.
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
const currentCourseIdVal = urlParams.get('course_id') || "alt-text-architects";
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
 * Sends an xAPI statement to the configured LRS.
 * Falls back to console logging if no LRS is configured.
 * @param {Object} verb - The xAPI verb object (id and display).
 * @param {Object} object - The xAPI object (id and definition).
 * @param {Object} [result] - Optional result object (score, success, completion).
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

    // Ensure the endpoint points to the statements resource
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

// Define common verbs
const XAPI_VERBS = {
    INITIALIZED: {
        "id": "http://adlnet.gov/expapi/verbs/initialized",
        "display": { "en-US": "initialized" }
    },
    EXPERIENCED: {
        "id": "http://adlnet.gov/expapi/verbs/experienced",
        "display": { "en-US": "experienced" }
    },
    INTERACTED: {
        "id": "http://adlnet.gov/expapi/verbs/interacted",
        "display": { "en-US": "interacted" }
    },
    COMPLETED: {
        "id": "http://adlnet.gov/expapi/verbs/completed",
        "display": { "en-US": "completed" }
    },
    ANSWERED: {
        "id": "http://adlnet.gov/expapi/verbs/answered",
        "display": { "en-US": "answered" }
    }
};

// Export to window for global access
window.xapi = {
    sendStatement,
    verbs: XAPI_VERBS,
    courseId: courseId
};
