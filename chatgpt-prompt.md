# Prompt: design a page for Moodle Custom Pages

Copy everything between the two lines into ChatGPT (or another AI assistant). Replace the text in [square brackets] first.

---

Design one web page and give it to me as a single complete HTML file in one code block, so I can save it as `design.html` and upload it to my Moodle site.

**What the page is for:** [for example: "A Trainer Resources hub for an Australian RTO with a hero banner, six resource cards (Lesson Plans, Assessment Tools, Presentations, Training Videos, Learner Activities, Trainer Guides), two featured resources and a 'Contact trainer support' box."]

**Look and feel:** [for example: "Clean and modern, blue accent #2563eb, soft rounded cards, plenty of white space."]

Rules (the page will be shown inside an existing Moodle site, below its own menu and page title):

1. Put all CSS in one `<style>` block in the `<head>`. No CSS frameworks or external stylesheets, except a Google Fonts `<link>` if you use a web font.
2. No JavaScript at all: no `<script>` tags and no `onclick` or other event attributes. Moodle will not run it. Leave out anything that needs JavaScript to work (search boxes, filter buttons, tabs, accordions, sliders, pop-ups). Hover effects and transitions in CSS are fine.
3. Style things with class names. Give every class a short unique prefix (for example `th-`) so they don't clash with Moodle's own classes such as `.card`, `.btn`, `.container` and `.row`.
4. Don't use `position: fixed` or `position: sticky`, and don't add a site header, navigation menu or footer. Moodle already has those. Don't add an `<h1>`: the page already has a title, so start headings at `<h2>`.
5. Make it responsive from a 360px phone screen up to a 1200px desktop (use `@media` queries), and respect `prefers-reduced-motion`.
6. Links: use `href="#"` wherever I need to add a real link later. If this is an index or hub page, link its cards to my existing pages listed below, using their exact web addresses. Only include the pages I name in "What the page is for", or all of them if I say so.
7. Images: use `https://` image URLs with a real description in `alt`, or use coloured panels and Unicode or emoji icons instead of images. No inline SVG icon libraries.
8. Readable text contrast (WCAG AA), descriptive link text (not "click here"), and Australian English spelling.

Give me only the HTML file and, below it, a short list of the links I still need to fill in.

My existing pages (name, web address, and who can see it):

[[PAGELIST]]

---

## In Moodle

1. Save ChatGPT's answer as a file ending in `.html`.
2. Go to *Site administration > Appearance > Custom pages* and click **New page** (or **Edit** on an existing page).
3. Under **Design**, upload the file and save. The page takes its look and content from the file, and the design only affects that page.
4. Use **Edit page** to change the text and fill in the `href="#"` links.

To restyle an existing page, upload a new design file. This replaces the page's content and its design CSS.

An index page only links to pages; it doesn't change who can see them. Give the index page an audience that matches the pages it links to, so people don't see cards for pages they can't open.
