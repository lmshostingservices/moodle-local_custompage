<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_custompage_upgrade($oldversion) {
    if ($oldversion < 2026062300) {
        upgrade_plugin_savepoint(true, 2026062300, 'local', 'custompage');
    }
    return true;
}
