# Superable Learning LMS — Course Packaging & Upload Guide

This walkthrough provides step-by-step instructions for tenant administrators on how to package, validate, upload, and manage custom e-learning courses via the Superable Learning Admin Dashboard.

---

## 1. Pre-Flight Course Package Checklist

Before compressing your course files, ensure your package meets the following requirements:

- [ ] **Manifest Location**: `course_structure.json` is placed in the root directory of your course files.
- [ ] **No Server Scripts**: The package contains NO `.php`, `.phtml`, `.sh`, `.exe`, `.cgi`, or `.htaccess` files (executable scripts cause automatic upload rejection).
- [ ] **Video Restriction Policy**: The package contains NO direct video files (`.mp4`, `.webm`, `.mov`, `.avi`). All course videos must be embedded using **YouTube** or **Vimeo** `<iframe>` links.
- [ ] **File Whitelist**: All files use approved extensions: `.json`, `.html`, `.css`, `.js`, `.png`, `.jpg`, `.jpeg`, `.svg`, `.webp`, `.gif`, `.mp3`, `.vtt`, `.woff2`.
- [ ] **Storage Quota**: The total uncompressed course size is within your tenant's **500 MB** storage limit.

---

## 2. Working with AI Assistants & Course Authoring Workflows

Creating a course package can be done easily using any of the following authoring workflows:

### Method A: Interactive Web Builder & Packager (`/packager.php`) (Recommended)
The easiest, zero-installation method for web-based AI chat outputs (ChatGPT, Gemini, Claude):
1. Open the [Modular Web Builder (`/packager.php`)](packager.php).
2. Paste metadata and module HTML/JSON fragments block-by-block into individual module cards.
3. Test modules live using **`👁️ Preview Module`** or **`▶️ Full Course Live Preview`** to verify formatting and component interactions before uploading.
4. Click **`📦 Generate & Download Course ZIP`** to produce a 1-click compliant course package.

### Method B: Web Chat UIs (ChatGPT, Gemini, Claude Web) — 1-Click Python ZIP Generator
If using a web browser chat window and prefer a script:
1. Ask the AI: *"Please output a single Python script `build_course.py` that embeds all JSON, CSS, and HTML module content and packages it directly into `course.zip`."*
2. Save the AI's script as `build_course.py` and run:
   ```bash
   python build_course.py
   ```
3. The script will automatically create the folder structure and output `course.zip` ready for upload—zero manual file copying required!

### Method C: AI Coding Agents & Harnesses (Antigravity CLI, Cursor, Claude Code, Windsurf)
If using an AI agent harness with file system capabilities:
1. Provide the agent with the [LLM_COURSE_BUILDER_INSTRUCTIONS.md](llm-course-builder-instructions.md) prompt file.
2. The agent will create all directories, files, and `.zip` archives on your computer automatically.

### Method D: LC-JSON 1.0 Standard Packages (`course.lc.json`)
Superable Learning LMS natively supports the **LC-JSON 1.0 Specification** ([LC_JSON_INTEGRATION_GUIDE.md](../integrations/lc-json-integration-guide.md)):
1. Export or create your course or question set in LC-JSON format (`course.lc.json` or `course.json`).
2. Compress the file into a `.zip` package (or upload it with any images/assets).
3. Upload the package via `/admin.php`. Superable Learning's built-in `LCJsonConverter` will automatically convert the LC-JSON document into a 100% WCAG 2.2 AA compliant course package!

---

## 3. How to Package Your Course (.ZIP Manually)

If creating files manually, the importer requires `course_structure.json` to be at the top level inside the `.zip` file.

```text
VALID ZIP ARCHIVE STRUCTURE:
my-course.zip
├── course_structure.json       <-- Must be in root of ZIP
├── css/
│   └── style.css
├── js/
│   └── main.js
├── images/
│   └── thumb.svg
└── modules/
    ├── welcome.html
    └── module1.html
```

### Steps to Compress Files:

#### On Windows:
1. Open your course folder.
2. Select all files and subfolders (`course_structure.json`, `css/`, `js/`, `images/`, `modules/`).
3. Right-click the selected items → select **Compress to ZIP file** (or Send to → Compressed (zipped) folder).
4. Name your archive (e.g. `my-accessible-course.zip`).

#### On macOS:
1. Open your course folder.
2. Select all files and subfolders (`course_structure.json`, `css/`, `js/`, `images/`, `modules/`).
3. Right-click the selected items → select **Compress X Items**.
4. Rename the generated `Archive.zip` file.

> **CRITICAL TIP**: Do NOT right-click and compress the parent outer folder itself. Open the folder, select its contents, and compress the contents so `course_structure.json` is at the root level of the ZIP archive.

---

## 4. Uploading via Tenant Admin Dashboard

1. **Log in to your Tenant Admin Panel**:
   - Visit `https://yourtenant.superablelearning.com/login.php` (or use your tenant link `?tenant=yourtenant`).
   - Log in using your client administrator credentials.

2. **Open the Admin Panel**:
   - Click **Tenant Admin Panel** in the top navigation bar (or navigate to `admin.php`).

3. **Locate the Course & Module Management Section**:
   - Look for the **Upload New Course (ZIP)** form box.
   - Click **Choose File** / **Browse** and select your `.zip` course package.

4. **Upload & Import**:
   - Click **Upload & Import Package**.
   - The system will inspect the archive, run security whitelisting checks, enforce path traversal protections, extract the files to your tenant's isolated directory, and run an automated accessibility and policy audit.

---

## 5. Understanding Import Notices & Audit Advisories

After uploading, a notice box will appear at the top of your Admin Dashboard:

- **System Notice (Green Banner)**: Indicates successful extraction and manifest registration.
- **System Error (Red Banner)**: Indicates an upload or security error (e.g. prohibited `.php` file detected or missing manifest).
- **Automated Audit & Policy Advisories (Orange Box)**:
  Displays non-blocking advisories regarding accessibility best practices or video embeds, such as:
  - `[Accessibility Notice] module1.html: Missing valid lang attribute on <html> tag.`
  - `[Accessibility Notice] module1.html: Contains 2 <img> tag(s) missing an alt attribute.`
  - `[Video Policy Alert] module1.html: Embedded iframe is not authorized. Only YouTube and Vimeo embeds are permitted.`

---

## 6. Configuring Course Settings & Access Modes

Once imported, your course will appear in the **Tenant Course Library** table on your Admin Panel:

### Configurable Settings:

1. **Course Title & Description**:
   - Edit the displayed title and description, then click **Save Manifest Changes**.
2. **Access Mode**:
   - **Public**: Course is accessible to all site visitors and guests.
   - **Protected**: Course requires user registration or an invitation key code.
   - **Teaser**: Course displays a teaser card on the dashboard with a custom **Course Info** link.
   - **Hidden**: Course is hidden from the public dashboard.
3. **Teaser Info Link**:
   - Specify a custom landing page or info URL for Teaser mode (e.g., `https://example.com/nvda-workshop-info`).
4. **External XCL Learning Link**:
   - Enter an external BuildXCL learning link (`https://content.buildxcl.com/?m=...`) to route course launch directly to BuildXCL.
5. **Automatic Backups**:
   - Overwriting an existing course automatically creates a restore point in `courses/tenants/{yourtenant}/.backups/`.

---

## 7. Troubleshooting Common Import Errors

| Error Message | Cause | Resolution |
| :--- | :--- | :--- |
| `course_structure.json manifest was not found` | `course_structure.json` is missing or nested inside a subfolder in the ZIP. | Ensure `course_structure.json` is at the root level of the ZIP file. |
| `Prohibited file type '.php'` | The ZIP contains executable server scripts. | Remove all `.php`, `.sh`, `.exe`, or script files from your package. |
| `Direct video upload (.mp4) is disabled` | The ZIP contains raw video files. | Remove raw video files and embed videos using YouTube or Vimeo `<iframe>` tags. |
| `Storage Quota Exceeded` | Your tenant has reached its **500 MB** storage limit. | Delete unused courses or compress large image assets to stay under 500 MB. |
| `Path traversal attempt detected` | ZIP entry contains relative pathing (`../`). | Re-create the ZIP file using standard archive tools. |
