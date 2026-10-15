<p align="center">
  <a href="https://lmshostingservices.com">
    <img src="https://raw.githubusercontent.com/lmshostingservices/lms-labs/main/attached_assets/lms-hosting-logo.png" alt="LMS Hosting Services" height="60">
  </a>
</p>

> **LMS Labs** is the Moodle plugin division of [LMS Hosting Services](https://lmshostingservices.com) — Australia's Moodle™ Certified Partner.

---

# Custom Pages (local_custompage)

Build site-level pages outside courses — a Help Centre, a Trainer Resources library, a Privacy Notice — and decide exactly who sees each one.

## What it does

There are two things you can make:

- **A resource hub** — one page that shows a card for each resource in it (a Trainer Resources library, a Help Centre). A 3-step guide walks you through it: **Details** (name, short introduction, who it is for, main menu), **Resources** (add each one: a page design made with ChatGPT, files, text, card summary, card image), and **Look** (a built-in look, or your own design file). In the main menu a hub becomes a dropdown, and each person only sees the cards and menu items they are allowed to open.
- **A single page** — one page on its own (a Privacy Notice, a policy), with a rich-text body, optional documents and Moodle blocks.

Every page reads like a modern intranet:

- **Always know where you are.** A breadcrumb (Intranet > hub > page) on every page, and beside each page the hub's contents with the current page marked and "Page 3 of 8". On phones, and on pages with their own design, the list folds into a bar above the page.
- **Move through a hub in order.** Previous and Next at the foot of each page; the last page leads back to the hub.
- **Long pages get an outline.** "On this page" lists the page's headings and follows you as you scroll (when the theme leaves room for it).
- **Intranet home.** One page listing every hub and page the person can open, with search and what changed recently. Name it and add it to the main menu in **Intranet settings**: the button on the Custom pages screen, or *Site administration > Plugins > Local plugins > Custom pages*.
- **Search.** Hubs with six or more pages, and hub lists with eight or more, get an instant filter.
- **Fresh and owned.** Each page shows when it was last updated, how long it takes to read and who looks after it. New and Updated badges mark recent changes. Set a *Review by* date and managers see when a review is overdue, on the page and in the list.
- **Was this page helpful?** People answer Yes or No and can add a comment. Totals appear in the Custom pages list, with every comment one click away.
- **Clean for readers.** Edit buttons only appear while Edit mode is on.

Also:

- **Your own design.** Upload an HTML file, or a .txt file holding the HTML (for example one made with ChatGPT), for a single page, a hub's banner or a hub resource, and its styles are applied to that page only, never to the rest of the site. Scripts and anything else that could run code are removed.
- **Ready-made ChatGPT prompt.** *Custom pages > Download the ChatGPT prompt* gives a prompt that produces design files that work first time. It includes the address of every page on your site, so ChatGPT can link an index or landing page to them.
- **Documents.** Attach PDFs, Office files, images, audio or video. The first PDF opens in a viewer; every file has a download link. Files (including images in the text) are only served to people who can see the page.
- **One place to manage.** *Site administration > Appearance > Custom pages* lists every hub, resource and single page with who can see it, its owner, review date and feedback, plus edit, reorder and delete.

## Requirements

- Moodle 4.4, 4.5, 5.0, 5.1, 5.2 or 5.3
- A Boost-based theme (tested with Boost and Academi)
- PHP 8.1 or later

## Installation

1. Upload the ZIP at *Site administration > Plugins > Install plugins*, or copy the `custompage` folder to `local/custompage`.
2. Visit *Site administration > Notifications* (or run `php admin/cli/upgrade.php`).

Upgrading from 1.x or 2.0 keeps every page, block, document and audience. Sections from earlier versions become resource hubs.

## Quick start: a Trainer Resources hub

1. *Custom pages > Create a resource hub*. **Details:** name it "Trainer Resources", add a one-line introduction, choose **People with certain roles** and pick the trainer role, and tick **Show in the main menu**.
2. **Resources:** add each resource — a name, a card summary, its files and/or text, and an optional card image. Reorder them with the arrows.
3. **Look:** pick TrainerSpace, Atelier, Studio or Classic (or upload your own design), then **Finish**.

Trainers now see **Trainer Resources** in the main menu: a banner and a card for each resource, opening to its page with its documents. Everyone else sees nothing.

## Who can see what

| Choice | Effect |
|---|---|
| Everyone who is logged in | Everyone with an account (not the guest user) |
| Everyone, including visitors who are not logged in | Also guests and anonymous visitors. If the site has *Force users to log in* on (the default for new Moodle 5.2+ sites), visitors log in first. |
| People with certain roles | Anyone holding one of the roles anywhere on the site: site level, a category or any course (for example every Teacher). All your site's roles are listed, including custom roles. |
| Chosen people | Individually chosen users |
| Only people who manage pages | Hidden from everyone else (useful while you build it) |

Resources in a hub follow the hub's choice. A single page can use the same choices; a page can also mix them (for example a role plus chosen people).

## Permissions

| Capability | Default | Allows |
|---|---|---|
| `local/custompage:manage` | Manager role (and site admins) | Create, edit, reorder and delete hubs and pages, download the ChatGPT prompt, and see every page |

## Notes

- Files placed inside a **Text block** are protected by login only (core Moodle behaviour for blocks outside courses). Put restricted files in the page's **Documents** or body.
- Boost hides breadcrumb items that already appear in the main menu, so a hub in the menu may not show in the breadcrumb.
- An uploaded design keeps its layout and styles, but search boxes, filters and other parts that need JavaScript will not work, because scripts are removed. The downloadable prompt tells ChatGPT not to use them.

## Privacy

The plugin stores who created and last changed each page and audience. Users can request export or deletion through Moodle's privacy tools.

## Pricing

**$50 USD** — one-time purchase per site · lifetime updates · no subscription.

Download at [lms-labs.com/plugins](https://lms-labs.com/plugins).


## ⭐ Why this plugin is unlike anything else available

**A full CMS with audience targeting inside Moodle**

- Sections and pages outside courses, each visible only to the people you choose: all logged-in users, guests and logged-out visitors, holders of a site role, or hand-picked people. A student sees a different set of pages than a trainer, who sees a different set than an RTO administrator.
- Sections show their pages as cards, so a Trainer Resources or Help Centre library looks finished without any design work.
- Documents and images on a page follow the same rules as the page, so restricted material stays restricted.
- Pages appear in the site's main menu alongside standard Moodle items, filtered per person.

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
