# Changelog

## [2.2.1] - 2026-10-15

### Fixed
- Release pipeline: every management page (index, wizard, feedback report, legacy edit address) now calls
  require_login() and require_capability() itself; PARAM_RAW on editor and CSS fields is documented for the pipeline.
- Coding style: multi-line calls start their arguments on a new line; one help string reworded.

## [2.2.0] - 2026-10-15

Turns custom pages into an intranet. Tested end to end on Moodle 4.4, 4.5, 5.0, 5.1, 5.2 and 5.3 with Boost and
Academi, and covered by PHPUnit.

### Added
- Reading layout for every page: breadcrumb (intranet home, hub, page), the hub's pages beside the page with the
  current one marked and "Page n of N", Previous and Next (the last page leads back to the hub), and an "On this page"
  outline for long pages. The layout follows the width the theme gives it, and folds the hub list into a bar on phones
  and on pages with their own design.
- Intranet home page (`/local/custompage/home.php`): hubs with a glimpse of their pages, single pages, recently
  updated, and search. Optional main menu link and a name of your choice (Intranet settings).
- Instant filters on hubs with six or more pages, in hub lists with eight or more, and on the home page.
- Page owner and "Review by" date on single pages, hub details and resources. Pages show when they were updated, the
  reading time and the owner; managers see overdue reviews on the page and in the Custom pages list.
- New and Updated badges and file type on hub cards.
- "Was this page helpful?" with an optional comment; totals in the Custom pages list and a report per page.
  Covered by the privacy provider (export and delete).

### Changed
- The theme's own title and breadcrumb are replaced on custom pages by the page's own (so the hub appears in the
  breadcrumb). The hub banner's label is now the hub name in sentence case and is the page heading.

## [2.1.3] - 2026-10-14

### Changed
- The Edit hub, Add a resource, Edit page and Custom pages buttons only appear while Edit mode is on, so managers see
  pages as everyone else does.
- Pages that show their own banner (the TrainerSpace, Atelier and Studio hub looks, or an uploaded design) no longer
  repeat the theme's page title above it outside Edit mode; the title is kept for screen readers.

## [2.1.2] - 2026-10-14

### Added
- Hub resources can be a page design: step 2 of the hub wizard has a **Page design** upload for an HTML file made with
  ChatGPT. It becomes the resource's page, with its styles kept to that page. (Files is for documents to download and
  does not accept web pages.)
- Design uploads (single pages, hub looks and resources) accept a .txt file holding the HTML. When the text is a saved
  ChatGPT answer, the code blocks are used and the explanation around them is ignored.

## [2.1.1] - 2026-10-14

Tested end to end on Moodle 4.4, 4.5, 5.0, 5.1, 5.2 and 5.3 with Boost and Academi, and covered by PHPUnit on each.

### Added
- Declared supported Moodle versions: 4.4 to 5.3.

### Fixed
- On sites with "Force users to log in" on (the default for new Moodle 5.2+ sites), the main menu offered pages open
  to guests to visitors who were not logged in; those links only led to the login page. They are now shown after login.
- Layout on Bootstrap 5 (Moodle 5.x): bold text, spacing, badges and screen-reader labels use both Bootstrap 4 and 5
  class names, card titles keep their weight under Academi 5, and the look choices in the hub wizard no longer overlap
  their radio buttons.

## [2.1.0] - 2026-10-14

Rethinks how hubs of pages are built, and lets any manager use designs made with ChatGPT. Tested end to end on
Moodle 4.4 and 4.5 with Boost and Academi, and covered by PHPUnit.

### Added
- **Create a resource hub**: a 3-step guide (Details, Resources, Look) that replaces "sections". Choosing who it is for is
  one question; resources follow the hub; the main menu dropdown and cards are filtered per person automatically.
- **Create a single page** for standalone pages.
- Built-in hub looks: Classic, TrainerSpace, Atelier and Studio, with a banner and resource count.
- Upload your own design (an HTML file): its CSS is scoped to that page, html/body/:root rules apply to the page wrapper,
  Google Fonts are kept, and scripts, event handlers and javascript: links are removed. Design CSS can be edited later
  and is checked before saving.
- Download a ChatGPT prompt that produces compatible design files and lists the address of every page on the site.

### Changed
- "Section" is now called "Resource hub" everywhere; the management list links hubs and resources to the guide.
- "Same as its section" is now "Same as its hub"; one chosen person reads "1 chosen person".

### Fixed
- Page HTML lost HTML5 elements (section, header, button…) when reopened in the editor.
- Minified CSS without a final semicolon swallowed the rules after a custom property.
- Theme heading colours overrode a design's own text colour.

## [2.0.0] - 2026-10-14

A rebuild of the editing experience and access rules. Tested end to end on Moodle 4.4 and 4.5 with Boost and Academi
(admin, manager, trainer role, student, guest and logged-out visitor) and covered by PHPUnit.

### Added
- Rich-text page body (and section introduction) with images, served with the page's access rules.
- New section / New page forms showing only the fields each one uses, with "Who can see this" in the same form.
- Pages inherit their section's audience by default, with an option to choose their own.
- Guest pages are visible to logged-out visitors (unless the site forces login).
- Management list showing who can see each item in plain English, with reorder and delete.
- Single capability `local/custompage:manage`, given to the Manager role by default.

### Changed
- Page names and menu labels are plain text (1.x HTML names are converted on upgrade).
- A section appears to anyone who can see one of its pages; empty sections are left out of the menu.
- Deleting a section moves its pages out and keeps who can see them; the confirmation says so.
- Editing happens from the page (Edit page) or the list (Edit); /local/custompage/edit.php redirects to the page.

### Removed
- The report-builder style editor (Content/Details/Audience/Access tabs), its templates, JavaScript and services.
- Capabilities `create`, `edit`, `editall` and `view`, and the site administrators audience (managers always see every page).

## [1.1.0] - 2026-10-14

Tested end to end on Moodle 4.4 (admin, trainer/manager, student and guest), PHPUnit suite passing.

### Added
- Container pages now list their child pages as cards (summary, optional image, "Open" link), filtered to the pages the viewer can access.
- Card summary and card image fields for content pages.
- Documents area on content pages: first PDF shown in an inline viewer plus download links. Served by the plugin with the page's audience rules.
- Containers in the primary navigation become dropdowns (Overview + visible child pages).

### Fixed
- "Invalid context level specified" on Moodle 4.4: the `\core\hook\after_config` hook only exists in 4.5, so the custom context level was never registered. Added the legacy `local_custompage_after_config()` callback for 4.4 and reset core's cached context levels after registering.
- Child pages never appeared in the navigation (each needed its own "Show in primary navigation" flag); pages could appear twice; the menu link tooltip read "top-level-item". Navigation now uses the `primary_extend` hook instead of rewriting `$CFG->custommenuitems`.
- Site administrators did not see containers whose children were audience-restricted to other roles.
- Pages list order was random (no ORDER BY); now ordered by hierarchy.
- "Edit page" opened the page without editing mode, and its URL was double-escaped.
- Details tab loaded its form with a second request, causing JavaScript errors when switching tabs; the form is now rendered with the tab.
- Container breadcrumb item had no link (hidden by Boost).
- PHP 8.2+ dynamic property deprecation in the page wrapper class.
- Audience tab empty message said "report" instead of "page".
- Autoloaded context class relied on `$CFG` being in scope.

### Removed
- Unprefixed global functions in lib.php (`build_page_hierarchy()` etc.) and the custom-menu rewriting hooks.

## [1.0.6] - 2026-10-14

### Fixed
- "New page", edit, delete and other actions did nothing. `local/selectors` only has a default export but is imported as a namespace (`import * as`), so its AMD build must return the default export from `define()` (as Moodle's grunt build does). Rebuilt with that behaviour.

## [1.0.5] - 2026-10-14

### Fixed
- Rebuilt all AMD modules in `amd/build/` from `amd/src/`. Several build files contained untranspiled ES module syntax (`import`/`export`), which broke Moodle's combined RequireJS bundle (`No define call for core/first`) and stopped JavaScript site-wide, including the admin menu. Removed stray non-minified `.js` files from `amd/build/`.

## [1.0.4] - 2026-10-13

### Changed
- Minimum Moodle version lowered from 4.5 (2024100700) to 4.4 (2024042200). All core APIs used are available in 4.4; the floor is set by the 4.4 hooks `after_config`, `before_http_headers` and `before_standard_top_of_body_html_generation`.

### Security
- Audience `classname` request parameter is now cleaned as PARAM_TEXT instead of PARAM_RAW_TRIMMED.
- `audiences\base::instance()` now only instantiates concrete subclasses of the audience base class, preventing arbitrary class instantiation from a submitted classname.
- `index.php` explicitly requires permission to view the pages list.

### Fixed
- Cache definition string renamed to `cachedef_custompage_allowed_pages` to match `db/caches.php`.
- Added GPL boilerplate and docblocks to `db/upgrade.php`.

## [1.0.1] - 2026-05-01

### Fixed
- Primary navigation now appears on admin/edit pages via `before_http_headers` hook
- Fixed `CONTEXT_CUSTOMPAGE` undefined constant error in namespaced context class
- Fixed `$PAGE->context` ordering in `view.php` to prevent filter warnings
- Fixed missing hook registrations in `db/hooks.php`
- Removed deprecated legacy callback causing Moodle 4.4+ warnings

## [1.0.0] - 2026-03-27

Initial stable release targeting Moodle 4.5+.

### Added
- Hierarchical custom page management with container and content page types
- Custom context (CONTEXT_CUSTOMPAGE) for full Moodle block support
- 5 audience types for access control: allusers, guests, admins, systemrole, manual
- Primary navigation integration via custom menu items
- Vancode-based sortthread ordering for efficient hierarchical sorting
- Report Builder integration with pages_list and page_access_list system reports
- 5 AJAX web services for page and audience management
- 7 event classes for page and audience lifecycle tracking
- Inline editing for page names, titles, and audience headings
- Privacy API implementation (GDPR compliant)
- Moodle 4.3+ hooks support with legacy callback fallback
- Caching infrastructure for page data and audiences
- PHPUnit test suite and Behat acceptance tests
