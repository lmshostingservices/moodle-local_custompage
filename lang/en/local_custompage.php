<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package    local_custompage
 * @category   string
 * @copyright  2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addaresource'] = 'Add a resource';
$string['addpage'] = 'Add page';
$string['addpagetosection'] = 'Add a page to this section';
$string['addresource'] = 'Add resource';
$string['andmore'] = 'and {$a} more';
$string['audience_allusers'] = 'All logged-in users';
$string['audience_guests'] = 'Guests and visitors';
$string['audience_guests_long'] = 'Guests and visitors who are not logged in';
$string['audience_managersonly'] = 'Only people who manage pages';
$string['audience_roles'] = 'Role: {$a}';
$string['audience_rolesname'] = 'People with these roles';
$string['audience_rolesname_help'] = 'Anyone who holds one of these roles anywhere on the site: at site level, in a category or in any course. For example, choosing Teacher includes everyone who is a teacher in at least one course. All of your site\'s roles are listed, including custom roles.';
$string['audience_sameassection'] = 'Same as its hub';
$string['audience_user'] = '1 chosen person';
$string['audience_users'] = '{$a} chosen people';
$string['audience_usersname'] = 'These people';
$string['audiencechoice_all'] = 'Everyone who is logged in';
$string['audiencechoice_managers'] = 'Only people who manage pages (draft)';
$string['audiencechoice_people'] = 'Specific people';
$string['audiencechoice_public'] = 'Everyone, including visitors who are not logged in';
$string['audiencechoice_roles'] = 'People with a role (for example Trainer)';
$string['audiencecreated'] = 'Custom page audience created';
$string['audiencedeleted'] = 'Custom page audience deleted';
$string['audiencemode'] = 'Who can see this page';
$string['audiencemode_inherit'] = 'Same as its hub';
$string['audiencemode_own'] = 'Choose who can see this page';
$string['audienceupdated'] = 'Custom page audience updated';
$string['backtohub'] = 'Back to';
$string['badge_new'] = 'New';
$string['badge_updated'] = 'Updated';
$string['breadcrumb'] = 'Breadcrumb';
$string['cachedef_custompage_allowed_pages'] = 'Custom pages each user can see';
$string['cannotbeselfparent'] = 'A page cannot be inside itself.';
$string['cannotmovedown'] = 'This item is already last.';
$string['cannotmoveup'] = 'This item is already first.';
$string['cardimage'] = 'Card image';
$string['cardimage_help'] = 'Shown at the top of this resource\'s card. Landscape images (16:9) look best. Without an image the card shows a coloured panel with the first letter of the name.';
$string['cardsettings'] = 'Card in its section';
$string['cardsettings_desc'] = 'When this page is in a resource hub, the hub shows it as a card with this summary and image.';
$string['cardsummary'] = 'Card summary';
$string['cardsummary_help'] = 'One or two sentences describing the resource, shown on its card.';
$string['choosedesignfile'] = 'Upload a design file, or choose a built-in look.';
$string['chooselook'] = 'Choose a look';
$string['choosepeople'] = 'Choose at least one person.';
$string['chooseroles'] = 'Choose at least one role.';
$string['circularreference'] = 'That would put the page inside one of its own pages.';
$string['comment'] = 'Comment';
$string['containerhaschildren'] = 'This section still has pages in it.';
$string['containermustberoot'] = 'A section cannot be inside another section.';
$string['createhub'] = 'Create a resource hub';
$string['createsinglepage'] = 'Create a single page';
$string['custompage'] = 'Custom page';
$string['custompage:manage'] = 'Manage custom pages';
$string['deleteconfirm'] = 'Delete "{$a->name}"? Its content, documents and blocks will be deleted.';
$string['deletepage'] = 'Delete page';
$string['deletesection'] = 'Delete hub';
$string['deletesectionconfirm'] = 'Delete the hub "{$a->name}"? Its {$a->count} resource(s) will not be deleted: they become single pages and keep the same people able to see them.';
$string['design'] = 'Design';
$string['design_desc'] = 'Start from a design file, for example one made with ChatGPT. <a href="{$a}">Download the prompt</a>, paste it into ChatGPT, save its answer as an .html (or .txt) file and upload it here.';
$string['designcss'] = 'Design CSS';
$string['designcss_help'] = 'Styles for this page only. It is limited to this page automatically, so rules for body, headings or links won\'t change the rest of the site. Uploading a design file replaces it.';
$string['designcsserror'] = 'This CSS can\'t be used: {$a}';
$string['designfile'] = 'Design file';
$string['designfile_help'] = 'An HTML file (.html, or a .txt file holding the HTML) with its CSS in a style block, such as one made with ChatGPT using the downloadable prompt. Uploading it replaces this page\'s content and design CSS. Scripts are removed; everything else is kept.';
$string['documents'] = 'Documents';
$string['documents_help'] = 'Files shown on the page: the first PDF opens in a viewer and every file has a download link. Only people who can see the page can open them. Web pages (.html) are not accepted here; upload a design made with ChatGPT as the page design instead.';
$string['documentviewer'] = 'Document viewer';
$string['downloadprompt'] = 'Download the ChatGPT prompt';
$string['edithub'] = 'Edit hub: {$a}';
$string['edithubbutton'] = 'Edit hub';
$string['editpage'] = 'Edit page';
$string['editresource'] = 'Edit resource';
$string['editsection'] = 'Edit section';
$string['editsettings'] = 'Edit';
$string['emptypage'] = 'This page is empty. Use Edit page to add content or documents.';
$string['emptysection'] = 'There is nothing here for you yet.';
$string['emptysection_manager'] = 'This hub has no resources yet. Use "Add a resource".';
$string['errormanage'] = 'You do not have permission to manage custom pages.';
$string['errorpageview'] = 'You cannot view this page.';
$string['eventcustompageviewed'] = 'Custom page viewed';
$string['feedback_answer'] = 'Answer';
$string['feedback_commentlabel'] = 'Anything the page owner should know? (optional)';
$string['feedback_for'] = 'Feedback: {$a}';
$string['feedback_helpful'] = 'Helpful';
$string['feedback_none'] = 'Nobody has answered yet.';
$string['feedback_nothelpful'] = 'Not helpful';
$string['feedback_question'] = 'Was this page helpful?';
$string['feedback_send'] = 'Send';
$string['feedback_sent'] = 'Thanks, your comment has been saved for the page owner.';
$string['feedback_sorry'] = 'Thanks for telling us. What was missing or unclear?';
$string['feedback_summary'] = '{$a->helpful} of {$a->total} found it helpful';
$string['feedback_thanks'] = 'Thanks for letting us know.';
$string['filterpages'] = 'Filter pages';
$string['finishhub'] = 'Finish';
$string['home_defaultlabel'] = 'Intranet';
$string['home_empty'] = 'There is nothing here for you yet.';
$string['home_empty_manager'] = 'Create a resource hub or a single page and it will appear here for the people who can see it.';
$string['home_hubs'] = 'Hubs';
$string['home_pages'] = 'Pages';
$string['home_recent'] = 'Recently updated';
$string['homeinmenu'] = 'Show the intranet home in the main menu';
$string['homeinmenu_desc'] = 'Adds a link to the intranet home page to the main menu for anyone who can open at least one page.';
$string['homeintro'] = 'Intranet introduction';
$string['homeintro_desc'] = 'Optional text at the top of the intranet home page.';
$string['homelabel'] = 'Intranet name';
$string['homelabel_desc'] = 'Used for the intranet home page, at the start of every breadcrumb and in the main menu. Leave empty for "Intranet".';
$string['homemenuroles'] = 'Show the intranet link to';
$string['homemenuroles_desc'] = 'Only people with one of these roles anywhere on the site (at site level, in a category or in any course) see the intranet link in the main menu. Site administrators and people who manage pages always see it. Choose none to show it to everyone who can open a page.';
$string['hub'] = 'Resource hub';
$string['hubaudience'] = 'Who is it for?';
$string['hubaudience_help'] = 'Everything in the hub uses this. People who manage custom pages always see everything.';
$string['hubinmenu_desc'] = 'Show this hub in the main menu, with a dropdown of its resources';
$string['hubintro'] = 'Short introduction';
$string['hubintro_help'] = 'One or two sentences shown at the top of the hub.';
$string['hubname'] = 'Hub name';
$string['hubname_help'] = 'For example "Trainer Resources" or "Help Centre". It\'s the page title and the menu item.';
$string['huboverview'] = 'Hub overview';
$string['hubready'] = 'Your hub is ready. People see a card for each resource they can open.';
$string['inmenu'] = 'In main menu';
$string['inthishub'] = 'Pages in this hub';
$string['intranetsettings'] = 'Intranet settings';
$string['invalidparent'] = 'Choose an existing section.';
$string['invalidsection'] = 'Choose an existing section.';
$string['look_atelier'] = 'Atelier';
$string['look_atelier_desc'] = 'Calm sage green with soft cards.';
$string['look_classic'] = 'Classic';
$string['look_classic_desc'] = 'Simple cards that match your theme.';
$string['look_custom'] = 'Your own design';
$string['look_custom_desc'] = 'Upload an HTML design file, for example one made with ChatGPT.';
$string['look_custom_help'] = '<a href="{$a}">Download the ChatGPT prompt</a>, describe the banner you want, save ChatGPT\'s answer as an .html (or .txt) file and upload it here. It appears above the resource cards and its colours and styles apply to the whole hub. Choosing a built-in look later removes it.';
$string['look_studio'] = 'Studio';
$string['look_studio_desc'] = 'Violet banner and rounded cards.';
$string['look_trainerspace'] = 'TrainerSpace';
$string['look_trainerspace_desc'] = 'Blue banner and clean white cards.';
$string['managecustompages'] = 'Custom pages';
$string['managehelp'] = 'A resource hub is a page that shows a card for each resource in it, and can appear in the main menu as a dropdown. A single page stands on its own. People who manage custom pages always see everything.';
$string['maxdepthreached'] = 'Pages can only be one level deep: a section, then its pages.';
$string['menulabel'] = 'Menu label';
$string['menulabel_help'] = 'Shorter text for the main menu. Leave empty to use the name.';
$string['minread'] = '{$a} min read';
$string['movedsuccess'] = 'Moved.';
$string['newpage'] = 'New page';
$string['newsection'] = 'New section';
$string['nextchooselook'] = 'Next: choose a look';
$string['nfiles'] = '{$a} file(s)';
$string['nomatchingpages'] = 'Nothing matches your search.';
$string['noowner'] = 'No owner';
$string['nopages'] = 'Nothing here yet. Start with "Create a resource hub".';
$string['noresourcesyet'] = 'No resources yet. Add the first one below.';
$string['nosection'] = 'Not in a hub (single page)';
$string['npages'] = '{$a} pages';
$string['nresources'] = '{$a} resources';
$string['onthispage'] = 'On this page';
$string['openpage'] = 'Open';
$string['overview'] = 'Overview';
$string['owner'] = 'Owner';
$string['owner_help'] = 'The person who keeps this page up to date. Their name is shown on the page.';
$string['ownership'] = 'Ownership';
$string['pagecontent'] = 'Page content';
$string['pagecontent_help'] = 'What people see on the page. You can format text and add images, links, tables and media.';
$string['pagecreated'] = 'Custom page created';
$string['pagedeleted'] = 'Custom page deleted';
$string['pagename'] = 'Name';
$string['pagenotfound'] = 'This page does not exist.';
$string['pageposition'] = 'Page {$a->position} of {$a->count}';
$string['pagerlabel'] = 'Previous and next page';
$string['pagesettings'] = 'Settings';
$string['pageupdated'] = 'Custom page updated';
$string['parentmustbecontainer'] = 'A page can only be placed in a section.';
$string['pluginname'] = 'Custom pages';
$string['privacy:metadata:audience'] = 'Who can see each custom page';
$string['privacy:metadata:audience:classname'] = 'The kind of audience';
$string['privacy:metadata:audience:configdata'] = 'The audience settings, such as chosen roles or people';
$string['privacy:metadata:audience:heading'] = 'Audience heading';
$string['privacy:metadata:audience:timecreated'] = 'When the audience was created';
$string['privacy:metadata:audience:timemodified'] = 'When the audience was last changed';
$string['privacy:metadata:audience:usercreated'] = 'The user who created the audience';
$string['privacy:metadata:audience:usermodified'] = 'The user who last changed the audience';
$string['privacy:metadata:feedback'] = 'Answers to "Was this page helpful?"';
$string['privacy:metadata:feedback:comment'] = 'The comment left with the answer';
$string['privacy:metadata:feedback:helpful'] = 'Whether the page was helpful';
$string['privacy:metadata:feedback:timecreated'] = 'When the answer was first given';
$string['privacy:metadata:feedback:timemodified'] = 'When the answer was last changed';
$string['privacy:metadata:feedback:userid'] = 'The person who answered';
$string['privacy:metadata:page'] = 'Custom pages';
$string['privacy:metadata:page:name'] = 'The page name';
$string['privacy:metadata:page:ownerid'] = 'The person responsible for keeping the page up to date';
$string['privacy:metadata:page:timecreated'] = 'When the page was created';
$string['privacy:metadata:page:timemodified'] = 'When the page was last changed';
$string['privacy:metadata:page:title'] = 'The page menu label';
$string['privacy:metadata:page:usercreated'] = 'The user who created the page';
$string['privacy:metadata:page:usermodified'] = 'The user who last changed the page';
$string['removedesign'] = 'Remove the uploaded design\'s styles (keep the text)';
$string['resourceadded'] = 'Added "{$a}". Add another, or go to the next step.';
$string['resourcedesign'] = 'Page design (optional)';
$string['resourcedesign_desc'] = 'Made this resource in ChatGPT? Upload its .html file (or a .txt file holding the HTML) here instead of under Files. <a href="{$a}">Download the prompt</a> for designs that work first time.';
$string['resourcedesign_help'] = 'An HTML page with its CSS in a style block, such as one made with ChatGPT. It becomes this resource\'s page: it replaces the text below, and its styles apply to this page only. Scripts are removed. Files is for documents to download (PDF, Word, PowerPoint and so on); web pages are not accepted there because they could run code on your site.';
$string['resourcefiles'] = 'Files';
$string['resourcename'] = 'Resource name';
$string['resourcesinhub'] = 'Resources in this hub';
$string['resourcetext'] = 'Text (optional)';
$string['resourcetext_help'] = 'Anything you want to show on the resource page, such as instructions, a video or links.';
$string['resourceupdated'] = 'Saved "{$a}".';
$string['reviewby'] = 'Review by';
$string['reviewby_help'] = 'When this page should next be checked. After this date, people who manage pages see that the review is overdue on the page and in the Custom pages list.';
$string['reviewon'] = 'Review by {$a}';
$string['reviewoverdue'] = 'Review was due {$a}';
$string['savenext'] = 'Save and add resources';
$string['searchhub'] = 'Search this hub';
$string['searchintranet'] = 'Search the intranet';
$string['section'] = 'Resource hub';
$string['section_help'] = 'The hub this page belongs to. It appears as a card in the hub and in the hub\'s menu dropdown. Choose "Not in a hub" for a page that stands alone.';
$string['sectionintro'] = 'Introduction';
$string['sectionintro_help'] = 'Optional text shown above the section\'s cards.';
$string['showinprimarynav'] = 'Main menu';
$string['showinprimarynav_desc'] = 'Show in the main menu';
$string['showinprimarynav_help'] = 'A section in the main menu appears as a dropdown listing its pages. People only see the items they are allowed to see.';
$string['step_details'] = 'Details';
$string['step_look'] = 'Look';
$string['step_resources'] = 'Resources';
$string['updated'] = 'Updated';
$string['updatedwhen'] = 'Updated {$a}';
$string['upkeep'] = 'Owner and feedback';
$string['viewintranethome'] = 'Open the intranet home';
$string['viewpage'] = 'View page';
$string['when_daysago'] = '{$a} days ago';
$string['when_today'] = 'today';
$string['when_yesterday'] = 'yesterday';
$string['whocansee'] = 'Who can see this';
$string['whocansee_desc'] = 'People who can manage custom pages always see everything. Tick nothing to keep this hidden from everyone else.';
