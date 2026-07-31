<p align="center">
  <a href="https://lmshostingservices.com">
    <img src="https://raw.githubusercontent.com/lmshostingservices/lms-labs/main/attached_assets/lms-hosting-logo.png" alt="LMS Hosting Services" height="60">
  </a>
</p>

> **LMS Labs** is the Moodle plugin division of [LMS Hosting Services](https://lmshostingservices.com) — Australia's Moodle™ Certified Partner.

---

# Custom Pages (local_custompage)

A Moodle plugin for creating and managing custom pages with block support, hierarchical organisation, audience-based access control, and primary navigation integration.

## Features

- **Hierarchical Page Management**: Create container and content pages with 2-level nesting
- **Block Support**: Full Moodle block support via custom context (CONTEXT_CUSTOMPAGE)
- **Audience-Based Access Control**: 5 audience types control who can view each page
  - All users (authenticated)
  - Guests
  - Administrators
  - System role holders
  - Manually selected users
- **Primary Navigation Integration**: Published pages appear in the site's primary navigation menu
- **Inline Editing**: Edit page names and titles directly from the management interface
- **Drag-and-Drop Ordering**: Reorder pages with move up/down actions and vancode-based sorting
- **Report Builder Integration**: Two system reports — pages list and page access list
- **Privacy API (GDPR)**: Full compliance with Moodle's Privacy API for user data export/deletion
- **Event System**: 7 event classes for page and audience lifecycle tracking
- **Moodle 4.3+ Hooks**: Modern hook-based navigation integration

## Requirements

- Moodle 4.5 or later
- PHP 8.1 or later

## Installation

### Via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code.
3. Check the plugin validation report and finish the installation.

### Manually

1. Copy the `custompage` folder to `local/custompage` in your Moodle installation.
2. Navigate to _Site administration > Notifications_ to complete the installation.

Or from the command line:

    php admin/cli/upgrade.php

## Configuration

After installation, go to _Site administration > Custom Pages_ to manage pages.

### Creating Pages

1. Click **Create new page** from the management interface
2. Choose page type: **Container** (holds child pages) or **Content** (displays blocks)
3. Set page name and title
4. Configure audience access rules

### Page Types

| Type | Description |
|------|-------------|
| Container | Groups child pages under a navigation heading |
| Content | Displays content via Moodle blocks |

### Audience Types

| Audience | Description |
|----------|-------------|
| All Users | Any authenticated user can view the page |
| Guests | Guest users can view the page |
| Admins | Only site administrators can view the page |
| System Role | Users with a specific system-level role can view the page |
| Manual | Individually selected users can view the page |

## Capabilities

| Capability | Type | Description |
|-----------|------|-------------|
| `local/custompage:create` | Write | Create new custom pages |
| `local/custompage:editall` | Write | Edit all custom pages |
| `local/custompage:edit` | Write | Edit own custom pages |
| `local/custompage:view` | Read | View custom pages |

## Web Services

The plugin provides 5 AJAX web services for the management interface:

- Delete audience
- Delete page
- Move page up
- Move page down
- Update sort order

## Privacy

This plugin stores personal data in the following tables:

- `local_custompage_audience`: Tracks audience membership for access control
- `local_custompages`: Records page creator and modifier information

Users can request export or deletion of their data through Moodle's privacy tools.

## Pricing

**$50 USD** — one-time purchase per site · lifetime updates · no subscription.

Download at [lms-labs.com/plugins](https://lms-labs.com/plugins).


## ⭐ Why this plugin is unlike anything else available

**A full CMS with audience targeting inside Moodle**

- No other Moodle plugin provides a complete page hierarchy with audience-based visibility. Custom Pages lets administrators create pages organised in a tree structure, each visible only to specified cohorts, roles, or capability holders. A student sees a different set of pages than a trainer, who sees a different set than an RTO administrator.
- Pages support Moodle blocks — all the same blocks available on a course page (upcoming events, latest news, quick links) can be placed on a custom page without coding.
- Navigation integration: custom pages appear in the primary site navigation alongside standard Moodle menu items. They are treated as first-class site pages, not linked external content.

## Support

- **Portal:** [lms-labs.com](https://lms-labs.com)
- **Email:** support@lmshostingservices.com
- **Website:** [lmshostingservices.com](https://lmshostingservices.com)

LMS Labs is the plugin division of LMS Hosting Services, Australia's Moodle™ Certified Partner.

## License

2024 BitAscii Solutions <bitascii.dev@gmail.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
