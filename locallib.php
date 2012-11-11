<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mod/taskchain/locallib.php
 *
 * @package    mod
 * @subpackage taskchain
 * @copyright  2010 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/lib/gradelib.php');
require_once($CFG->dirroot.'/mod/taskchain/lib.php');
require_once($CFG->dirroot.'/mod/taskchain/locallib/base.php');

/**
 * mod_taskchain
 *
 * @copyright  2010 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage taskchain
 */
class mod_taskchain extends taskchain_base {

    /** @var boolean cache of switch to show if user can start this taskchain */
    public $canstart     = null;

    public $userid       = 0; // cache $USER->userid
    public $realuserid   = 0; // cache $USER->realuser

    public $time         = 0; // the time this page was displayed
    public $inpopup      = 0;
    public $action       = ''; // 'add', 'update', 'delete', 'deleteall', 'deleteconfirmed', 'deletecancelled'
    public $tab          = ''; // 'info', 'preview'
    public $mode         = ''; // e.g. report name
    public $confirmed    = 0;
    public $selected     = null; // array of ids of selected records

    public $course       = null;
    public $module       = null;
    public $coursemodule = null;
    public $taskchain    = null;
    public $chain        = null;
    public $task         = null;
    public $condition    = null;
    public $chaingrade   = null;
    public $chainattempt = null;
    public $taskscore    = null;
    public $taskattempt  = null;
    public $block        = null;
    public $gradeitem    = null;

    public $lastchainattempt = null;
    public $lasttaskattempt  = null;

    public $tasks         = null;
    public $conditions    = null;
    public $chaingrades   = null;
    public $chainattempts = null;
    public $taskscores    = null;
    public $taskattempts  = null;

    public $taskchains    = null;
    public $chains        = null;

    public $mycourses     = null;
    public $mytaskchains  = null;

    public $pageid        = '';
    public $pageclass     = '';

    /** child objects to simulate multiple inheritance */
    public $available     = null;
    public $can           = null;
    public $create        = null;
    public $get           = null;
    public $regrade       = null;
    public $require       = null;
    public $url           = null;

    /** fields to allow forced values for cnumber, tnumber and taskid */
    public $forcecnumber = 0;
    public $forcetnumber = 0;
    public $forcetaskid  = 0;

    /** settings for allowfreeaccess */
    public $maxchainattemptgrade = null;
    public $chaincompleted  = null;

    public $availabletaskid = 0;    // id of first available task
    public $availabletaskids = null; // ids of all available tasks
    public $countavailabletaskids = 0;   // number of availabletasks

    // array of arrays (by taskid)
    public $cache_taskattempts = array();
    public $cache_taskattemptsusort = array();

    public $cache_preconditions  = array();
    public $cache_postconditions = array();
    public $cache_available_task = array();

    public $conditiontype   = 0;  // CONDITIONTYPE_PRE or CONDITIONTYPE_POST
    public $columnlisttype  = ''; // "task" or "chain"
    public $columnlistid    = '';

    /** maintain statistics on deleted records */
    public $deleted       = null;

    /** properties that should be replaced by methods: $this->get_xxx() */
    /* =================================== *\
    public $chainid = 0;
    public $cnumber = 0;
    public $taskid  = 0;
    public $tnumber = 0;
    public $conditionid    = 0;
    public $chaingradeid   = 0;
    public $chainattemptid = 0;
    public $taskscoreid    = 0;
    public $taskattemptid  = 0;
    \* =================================== */

    /**
     * Constructor function for this class
     */
    public function __construct($dbrecord=null) {
        global $CFG, $DB, $PAGE, $USER;

        parent::__construct();

        $this->userid = $USER->id;
        if (isset($USER->realuser)) {
            $this->realuserid = $USER->realuser;
        }

        // get $pageid and $pageclass of current Moodle page
        list($pageid, $pageclass) = $this->get_pageid_pageclass();

        // do TaskChain initialization if this is a TaskChain page
        // i.e. don't initalize for backup, restore or upgrade

        if ($pageclass=='mod-taskchain' || $pageclass=='mod-taskchain-edit' || $pageclass=='course') {

            // get input params passed to this page
            $course         = optional_param('course',         0, PARAM_INT);
            $courseid       = optional_param('courseid', $course, PARAM_INT);
            $coursemoduleid = optional_param('cm',             0, PARAM_INT);
            $taskchainid    = optional_param('tc',             0, PARAM_INT);
            $chainid        = optional_param('chainid',        0, PARAM_INT);
            $taskid         = optional_param('taskid',         0, PARAM_INT);
            $conditionid    = optional_param('conditionid',    0, PARAM_INT);
            $chaingradeid   = optional_param('chaingradeid',   0, PARAM_INT);
            $chainattemptid = optional_param('chainattemptid', 0, PARAM_INT);
            $taskscoreid    = optional_param('taskscoreid',    0, PARAM_INT);
            $taskattemptid  = optional_param('taskattemptid',  0, PARAM_INT);
            $blockid        = optional_param('blockid',        0, PARAM_INT);

            //get main id for this page
            switch ($pageid) {
                case 'course-mod':
                    $coursemoduleid = optional_param('delete', $coursemoduleid, PARAM_INT);
                    break;
                case 'course-modedit':
                    $coursemoduleid = optional_param('update', $coursemoduleid, PARAM_INT);
                    break;
                case 'mod-taskchain-edit-columnlists':
                case 'mod-taskchain-edit-chains':
                case 'mod-taskchain-index':
                case 'backup-backup':
                    $courseid = optional_param('id', $courseid, PARAM_INT);
                    break;
                case 'mod-taskchain-edit-tasks':
                case 'mod-taskchain-report':
                case 'mod-taskchain-view':
                case 'mod-taskchain-attempt':
                    $coursemoduleid = optional_param('id', $coursemoduleid, PARAM_INT);
                    break;
                case 'mod-taskchain-edit-task':
                    $taskid = optional_param('id', $taskid, PARAM_INT);
                    break;
                case 'mod-taskchain-submit':
                    $taskattemptid = optional_param('id', $taskattemptid, PARAM_INT);
                    break;
                case 'mod-taskchain-edit-condition':
                    $conditionid = optional_param('id', $conditionid, PARAM_INT);
                    break;
                case 'mod-taskchain-mod':
                    $coursemoduleid = optional_param('coursemodule', $coursemoduleid, PARAM_INT);
                    break;
                default:
                    throw new moodle_exception('error_unrecognizedpageid', 'taskchain', '', $pageid);

            }

            // define main select criteria
            $select = array();
            $allowtaskid = false;
            switch (true) {
                case $taskattemptid>0  : $select[] = 'tc_tsk_att.id='.$taskattemptid; break;
                case $taskscoreid>0    : $select[] = 'tc_tsk_scr.id='.$taskscoreid;   break;
                case $chainattemptid>0 : $select[] = 'tc_chn_att.id='.$chainattemptid; $allowtaskid = true; break;
                case $chaingradeid>0   : $select[] = 'tc_chn_grd.id='.$chaingradeid;   $allowtaskid = true; break;
                case $conditionid>0    : $select[] = 'tc_cnd.id='.$conditionid;       break;
                case $taskid>0         : $allowtaskid = true;                         break;
                case $chainid>0        : $select[] = 'tc_chn.id='.$chainid;           break;
                case $taskchainid>0    : $select[] = 'tc.id='.$taskchainid;           break;
                case $coursemoduleid>0 : $select[] = 'cm.id='.$coursemoduleid;        break;
                case $courseid>0       : $select[] = 'c.id='.$courseid;               break;
            }
            if ($allowtaskid && $taskid>0) {
                $select[]= "tc_tsk.id=$taskid";
            }

            // ====================
            // Define join criteria
            // ====================
            // the most junior, i.e furthest to the right in the following list,
            // record that is required will be joined to all its parent records
            // User tables:
            //     chain_grades, chain_attempts, task_scores, task_attempts
            // Task tables:
            //     course, course_modules, taskchain, taskchain_chains, taskchain_tasks, taskchain_conditions
            // In addition ...
            //     chain_grades/attempts will be joined to their corresponding chain record
            //     task_scores/attempts will be joined to their corresponding task record
            //     the $tablesnames array stores the mapping: $tablename => $table
            $jointask = false;
            $joinchain = false;
            $tablenames = array();

            switch (true) {
                case $taskattemptid>0:
                    $tablenames['taskchain_task_attempts'] = 'tc_tsk_att';
                    $select[] = 'tc_tsk_att.taskid=tc_tsk_scr.taskid AND tc_tsk_att.cnumber=tc_tsk_scr.cnumber AND tc_tsk_att.userid=tc_tsk_scr.userid';
                case $taskscoreid>0:
                    $tablenames['taskchain_task_scores'] = 'tc_tsk_scr';
                    $select[] = 'tc_tsk_scr.taskid=tc_tsk.id AND tc_tsk.chainid=tc_chn_att.chainid AND tc_tsk_scr.cnumber=tc_chn_att.cnumber AND tc_tsk_scr.userid=tc_chn_att.userid';
                    $jointask = true;
                case $chainattemptid>0:
                    $tablenames['taskchain_chain_attempts'] = 'tc_chn_att';
                    $select[] = 'tc_chn_att.chainid=tc_chn.id AND tc_chn_att.userid=tc_chn_grd.userid';
                case $chaingradeid>0:
                    $tablenames['taskchain_chain_grades'] = 'tc_chn_grd';
                    $select[] = 'tc_chn_grd.parenttype=tc_chn.parenttype AND tc_chn_grd.parentid=tc_chn.parentid';
                case $taskchainid>0:
                case $coursemoduleid>0:
                    $joinchain = true;
            }

            switch (true) {
                case $conditionid>0:
                    $tablenames['taskchain_conditions'] = 'tc_cnd';
                    $select[] = 'tc_cnd.taskid=tc_tsk.id';
                case $jointask:
                case $taskid>0:
                    $tablenames['taskchain_tasks'] = 'tc_tsk';
                    $select[] = 'tc_tsk.chainid=tc_chn.id';
                case $joinchain:
                case $chainid>0:
                    $tablenames['taskchain_chains'] = 'tc_chn';
                    $select[] = 'tc_chn.parenttype=0 AND tc_chn.parentid=tc.id';
                    $tablenames['taskchain'] = 'tc';
                    $select[] = 'tc.id=cm.instance AND cm.module=m.id';
                    $tablenames['modules'] = 'm';
                    $select[] = "m.name='taskchain'";
                    $tablenames['course_modules'] = 'cm';
                    $select[] = 'cm.course=c.id';
                case $courseid>0:
                    $tablenames['course'] = 'c';
            }

            // define names and aliases for tables and fields
            $tables = array();
            $fields = array();
            foreach ($tablenames as $tablename=>$table) {
                $tables[] = '{'.$tablename.'} '.$table;

                if ($columns = $DB->get_columns($tablename)) {
                    foreach ($columns as $column) {
                        $field = strtolower($column->name);
                        $fields[] = $table.'.'.$field.' AS '.$table.'_'.$field;
                    }
                }

                // remove final "s" to get $classname
                if (substr($tablename, -1)=='s') {
                    $classname = substr($tablename, 0, -1);
                } else {
                    $classname = $tablename;
                }

                // remove initial "taskchain_" to get $propertyname
                if (substr($classname, 0, 10)=='taskchain_') {
                    $propertyname = substr($classname, 10);
                } else {
                    $propertyname = $classname;
                }

                // remove any remaining underscores,  "_"
                $propertyname = str_replace('_', '', $propertyname);

                // create new property of the appropriate class
                if ($propertyname=='course' || $propertyname=='coursemodule' | $propertyname=='module') {
                    $this->$propertyname = new stdClass();
                } else {
                    $this->$propertyname = new $classname(null, array('TC' => &$this));
                }
            }

            // check we had some sensible input
            if (empty($select) || empty($tables)) {
                throw new moodle_exception('error_noinputparameters', 'taskchain');
            }
            if (empty($fields)) {
                throw new moodle_exception('error_nodatabaseinfo', 'taskchain');
            }

            // get the information from the database
            if (! $record = $DB->get_record_sql('SELECT '.implode(',', $fields).' FROM '.implode(',', $tables).' WHERE '.implode(' AND ', $select))) {
                throw new moodle_exception('error_norecordsfound', 'taskchain');
            }

            // distribute the database information into the relevant objects
            foreach(get_object_vars($record) as $field=>$value) {
                $pos = strrpos($field, '_');
                $table = substr($field, 0, $pos);
                $field = substr($field, $pos + 1);
                $set_field = 'set_'.$field;
                switch ($table) {
                    case 'tc_tsk_att': $this->taskattempt->$field = $value;  break;
                    case 'tc_tsk_scr': $this->taskscore->$field = $value;    break;
                    case 'tc_chn_att': $this->chainattempt->$field = $value; break;
                    case 'tc_chn_grd': $this->chaingrade->$field = $value;   break;
                    case 'tc_cnd':     $this->condition->$field = $value;    break;
                    case 'tc_tsk':     $this->task->$field = $value;         break;
                    case 'tc_chn':     $this->chain->$field = $value;        break;
                    case 'tc':         $this->taskchain->$field = $value;    break;
                    case 'cm':         $this->coursemodule->$field = $value; break;
                    case 'm':          $this->module->$field = $value;       break;
                    case 'c':          $this->course->$field = $value;       break;
                }
            }

            // get course context
            $this->course->context = $this->context(CONTEXT_COURSE, $this->course->id);

            // mimic get_coursemodule_from_id() and get_coursemodule_from_instance()
            if ($this->coursemodule) {
                $this->coursemodule->name = $this->taskchain->name;
                $this->coursemodule->modname = $this->module->name;
                $this->coursemodule->context = $this->context(CONTEXT_MODULE, $this->coursemodule->id);
            }

            // the main objects have now been set up - yay !

            // reclaim some memory
            unset($record, $tablenames, $tablename, $tables, $table, $fields, $field, $select, $allowtaskid, $jointask, $joinchain);

            // we should at least have a course record by now
            if (empty($this->course->id)) {
                throw new moodle_exception('error_nocourseid', 'taskchain');
            }

            // require_login must come before inclusion of script libraries
            // so that correct language is set for calls to get_string() in the
            // included libraries
            if (substr($pageclass, 0, 13)=='mod-taskchain') {
                require_login($this->course->id, true, $this->coursemodule);
            }

            // create secondary objects
            $this->available = new taskchain_available(null, array('TC' => &$this));
            $this->can       = new taskchain_can(null,       array('TC' => &$this));
            $this->create    = new taskchain_create(null,    array('TC' => &$this));
            $this->get       = new taskchain_get(null,       array('TC' => &$this));
            $this->regrade   = new taskchain_regrade(null,   array('TC' => &$this));
            $this->require   = new taskchain_require(null,   array('TC' => &$this));
            $this->url       = new taskchain_url(null,       array('TC' => &$this));

            // check capabilities ("true" means "require")
            switch ($pageid) {
                case 'mod-taskchain-view':
                    $this->can->attempt() || $this->can->view(true);
                    break;
                case 'mod-taskchain-attempt':
                    $this->can->attempt(true);
                    break;
                case 'mod-taskchain-report':
                    $this->can->viewreports() || $this->can->reviewmyattempts(true);
                    break;
                case 'mod-taskchain-edit-condition':
                case 'mod-taskchain-edit-task':
                case 'mod-taskchain-edit-tasks':
                case 'course-modedit':
                    $this->can->manage(true);
                    break;
                case 'mod-taskchain-index':
                    $this->can->view(true);
                    break;
                case 'mod-taskchain-edit-columnlists':
                    $this->can->manage() || $this->can->manageactivities(true);
                    break;
            }

            // set tab and other page settings
            switch ($pageid) {
                case 'mod-taskchain-edit-tasks' : $this->tab = 'edit'  ;  break;
                case 'mod-taskchain-report'    : $this->tab = 'report';  break;
                case 'mod-taskchain-attempt'   : $this->tab = 'attempt'; break;
                case 'mod-taskchain-submit'    : $this->tab = 'submit';  break;
                default: $this->tab = optional_param('tab', 'info', PARAM_ALPHA);
            }

            $this->action = optional_param('action', '', PARAM_ALPHA);
            $this->inpopup = optional_param('inpopup', 0, PARAM_INT);
            $this->confirmed = optional_param('confirmed', 0, PARAM_INT);
            $this->selected  = taskchain_optional_param_array('selected', 0, PARAM_INT);

            // set conditiontype
            $type = ($this->condition ? $this->condition->conditiontype : 0);
            $this->conditiontype = optional_param('conditiontype', $type, PARAM_INT);

            // set columnlisttype and columnlistid
            switch ($pageid) {
                case 'mod-taskchain-edit-chains': $type = 'chains'; break;
                case 'mod-taskchain-edit-tasks':  $type = 'tasks';  break;
                default: $type = '';
            }
            $type = optional_param('columnlisttype', $type, PARAM_ALPHA);
            if ($type) {
                $this->columnlisttype = $type;
                $this->columnlistid = get_user_preferences('taskchain_'.$type.'_columnlistid', 'default');
                $this->columnlistid = optional_param('columnlistid', $this->columnlistid, PARAM_ALPHA);
            }

            if ($this->action=='' && ($pageclass=='mod-taskchain' || $pageclass=='mod-taskchain-edit')) {
                $actions = array('add',
                                 'update',
                                 'delete',
                                 'deleteall',
                                 'deleteconfirmed',
                                 'deletecancelled');
                foreach ($actions as $action) {
                    if (optional_param($action, '', PARAM_RAW)) {
                        $this->action = $action;
                        break;
                    }
                }
            }

            if ($this->action) {
                require_sesskey();
            }

            $this->force_cnumber(optional_param('cnumber', 0, PARAM_INT));
            $this->force_tnumber(optional_param('tnumber', 0, PARAM_INT));
            $this->force_taskid(optional_param('taskid', 0, PARAM_INT));

            // store the time this page was created
            $this->time  = time();

        } // end if $pageid == mod-taskchain
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Magic methods                                                              //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * __call
     *
     * here is one way to implement inheritance from multiple classes
     * this allows us to separate the methods into several classes
     * http://stackoverflow.com/questions/356128/can-i-extend-a-class-using-more-than-1-class-in-php
     *
     * taskchain::available_xxx() will call taskchain_available::xxx()
     * taskchain::can_xxx()       will call taskchain_can::xxx()
     * taskchain::create_xxx()    will call taskchain_create::xxx()
     * taskchain::get_xxx()       will call taskchain_get::xxx()
     * taskchain::regrade_xxx()   will call taskchain_regrade::xxx()
     * taskchain::require_xxx()   will call taskchain_require::xxx()
     *
     * @param string $name
     * @param array $params
     * @todo Finish documenting this function
     */
    static public function __callStatic($name, $params) {
        switch (true) {
            case substr($name, 0, 10)=='available_' : $callback = array('taskchain_available', substr($name, 10)); break;
            case substr($name, 0,  4)=='can_'       : $callback = array('taskchain_can',       substr($name,  4)); break;
            case substr($name, 0,  7)=='create_'    : $callback = array('taskchain_create',    substr($name,  7)); break;
            case substr($name, 0,  4)=='get_'       : $callback = array('taskchain_get',       substr($name,  4)); break;
            case substr($name, 0,  8)=='regrade_'   : $callback = array('taskchain_regrade',   substr($name,  8)); break;
            case substr($name, 0,  8)=='require_'   : $callback = array('taskchain_require',   substr($name,  8)); break;
            case substr($name, 0,  4)=='url_'       : $callback = array('taskchain_url',       substr($name,  4)); break;
            default: return false; // shouldn't happen !!
        }
        return call_user_func_array($callback, $params);
    }

    /**
     * __call
     *
     * here is one way to implement inheritance from multiple classes
     * this allows us to separate the methods into several classes
     * http://stackoverflow.com/questions/356128/can-i-extend-a-class-using-more-than-1-class-in-php
     *
     * taskchain->available_xxx() will call taskchain->available->xxx()
     * taskchain->can_xxx()       will call taskchain->can->xxx()
     * taskchain->create_xxx()    will call taskchain->create->xxx()
     * taskchain->get_xxx()       will call taskchain->get->xxx()
     * taskchain->regrade_xxx()   will call taskchain->regrade->xxx()
     * taskchain->require_xxx()   will call taskchain->require->xxx()
     *
     * @param string $name
     * @param array $params
     * @todo Finish documenting this function
     */
    public function __call($name, $params) {
        switch (true) {
            case substr($name, 0, 10)=='available_' : $callback = array($this->available, substr($name, 10)); break;
            case substr($name, 0,  4)=='can_'       : $callback = array($this->can,       substr($name,  4)); break;
            case substr($name, 0,  7)=='create_'    : $callback = array($this->create,    substr($name,  7)); break;
            case substr($name, 0,  4)=='get_'       : $callback = array($this->get,       substr($name,  4)); break;
            case substr($name, 0,  8)=='regrade_'   : $callback = array($this->regrade,   substr($name,  8)); break;
            case substr($name, 0,  8)=='require_'   : $callback = array($this->require,   substr($name,  8)); break;
            case substr($name, 0,  4)=='url_'       : $callback = array($this->url,       substr($name,  4)); break;
            default: return false; // shouldn't happen !!
        }
        return call_user_func_array($callback, $params);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Static methods                                                             //
    ////////////////////////////////////////////////////////////////////////////////

    // - set_user_editing()

    // the following methods are passed to taskchain_available
    // class in "mod/taskchain/locallib/available.php"
    // - available_navigations_list()
    // - available_feedbacks_list()
    // - available_mediafilters_list()
    // - available_outputformats_list($sourcetype)
    // - available_attemptlimits_list()
    // - available_allowresumes_list()
    // - available_grademethods_list()
    // - available_attemptgrademethods_list($type='grade')
    // - available_statuses_list()
    // - available_namesources_list()
    // - available_titles_list()
    // - available_addtypes_list()
    // - available_gradeweightings_list()
    // - available_gradelimits_list()
    // - available_studentfeedbacks_list()

    // - get_classes($plugintype, $classfilename='class.php', $prefix='', $suffix='')
    // - get_sourcetype($sourcefile)
    // - get_js_module(array $requires = null, array $strings = null)
    // - get_version_info($info)
    // - filearea_options()
    // - text_editor_options($context)
    // - text_page_types()
    // - filearea_types()
    // - text_page_options($type)
    // - window_options($type='')
    // - user_preferences_fieldnames_chain()
    // - user_preferences_fieldnames_task()
    // - get_question_text($question)
    // - string_ids($field_value, $max_field_length=255)
    // - string_id($str)

    // - format_status($status)
    // - format_time($time, $format=null, $notime='&nbsp;')
    // - format_score($record, $default='&nbsp;')


    /**
     * set_user_editing
     *
     * @uses $USER
     * @todo Finish documenting this function
     */
    static public function set_user_editing() {
        global $USER;
        $editmode = optional_param('editmode', null, PARAM_BOOL);
        if (! is_null($editmode)) {
            $USER->editing = $editmode;
        }
    }

    /**
     * This function will "include" all the files matching $classfilename for a given a plugin type
     * (e.g. taskchainsource), and return a list of classes that were included
     *
     * @uses $CFG
     * @param string $plugintype one of the plugintypes specified in mod/taskchain/db/subplugins.php
     * @param xxx $classfilename (optional, default='class.php')
     * @param xxx $prefix (optional, default='')
     * @param xxx $suffix (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function get_classes($plugintype, $classfilename='class.php', $prefix='', $suffix='') {
        global $CFG;

        // initialize array to hold class names
        $classes = array();

        // get list of all subplugins
        $subplugins = array();
        include($CFG->dirroot.'/mod/taskchain/db/subplugins.php');

        // extract the $plugintype we are interested in
        $types = array();
        if (isset($subplugins[$plugintype])) {
            $types[$plugintype] = $subplugins[$plugintype];
        }
        unset($subplugins);

        // we are not interested in these directories (or any beginning with ".")
        $ignored = array('CVS', '_vti_cnf', 'simpletest', 'db', 'yui', 'phpchain');

        // get all the subplugins for this $plugintype
        while (list($type, $dir) = each($types)) {
            $fulldir = $CFG->dirroot.'/'.$dir;
            if (is_dir($fulldir) && file_exists($fulldir.'/'.$classfilename)) {

                // include the class
                require_once($fulldir.'/'.$classfilename);

                // extract class name, e.g. taskchain_source_hp_6_jcloze_xml
                // from $subdir, e.g. mod/taskchain/file/h6/6/jcloze/xml
                // by removing leading "mod/" and converting all "/" to "_"
                $classes[] = $prefix.str_replace('/', '_', substr($dir, 4)).$suffix;

                // get subplugins in this $dir
                $items = new DirectoryIterator($fulldir);
                foreach ($items as $item) {
                    if (substr($item, 0, 1)=='.' || in_array($item, $ignored)) {
                        continue;
                    }
                    if ($item->isDir()) {
                        $types[$type.$item] = $dir.'/'.$item;
                    }
                }
            }
        }
        sort($classes);
        return $classes;
    }

    /**
     * Detects the type of the source file
     *
     * @param stored_file $sourcefile the file that has just been uploaded and stored
     * @return string the type of the source file (e.g. hp_6_jcloze_xml)
     */
    static public function get_sourcetype($sourcefile) {
        // include all the taskchain_source classes
        $classes = self::get_classes('taskchainsource');

        // loop through the classes checking to see if this file is recognized
        // use call_user_func() to prevent syntax error in PHP 5.2.x
        foreach ($classes as $class) {
            if (call_user_func(array($class, 'is_taskfile'), $sourcefile)) {
                return call_user_func(array($class, 'get_type'), $class);
            }
        }

        // file is not a recognized task type :-(
        return '';
    }

    /**
     * Returns a js module object for the TaskChain module
     *
     * @param array $requires
     *    e.g. array('base', 'dom', 'event-delegate', 'event-key')
     * @return array $strings
     *    e.g. array(
     *        array('timesup', 'task'),
     *        array('functiondisabledbysecuremode', 'task'),
     *        array('flagged', 'question')
     *    )
     */
    static public function get_js_module(array $requires=null, array $strings=null) {
        return array('name'     => 'mod_taskchain',
                     'fullpath' => '/mod/taskchain/module.js',
                     'requires' => $requires,
                     'strings'  => $strings);
    }

    /**
     * get_version_info
     *
     * @uses $CFG
     * @param xxx $info
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function get_version_info($info)  {
        global $CFG;

        static $module = null;
        if (is_null($module)) {
            $module = new stdClass();
            require($CFG->dirroot.'/mod/taskchain/version.php');
        }

        if (isset($module->$info)) {
            return $module->$info;
        } else {
            return "no $info found";
        }
    }

   /**
    * load_mediafilter_filter
    *
    * @param xxx $classname
    * @todo Finish documenting this function
    */
   static public function load_mediafilter_filter($classname)  {
        global $CFG;
        $path = $CFG->dirroot.'/mod/taskchain/mediafilter/'.$classname.'/class.php';

        // check the filter exists
        if (! file_exists($path)) {
            debugging('taskchain mediafilter class is not accessible: '.$classname, DEBUG_DEVELOPER);
            return false;
        }

        return require_once($path);
    }

    /**
     * filearea_options
     *
     * @uses $CFG
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function filearea_options($context=null) {
        global $CFG;
        require_once($CFG->dirroot.'/repository/lib.php');

        $types = 0;
        if (defined('FILE_INTERNAL')) {
            $types = $types | FILE_INTERNAL; // Moodle 2.x
        }
        if (defined('FILE_EXTERNAL')) {
            $types = $types | FILE_EXTERNAL; // Moodle 2.x
        }
        if (defined('FILE_REFERENCE')) {
            $types = $types | FILE_REFERENCE; // Moodle 2.3 and later
        }

        return array('context'      => $context,
                     'maxbytes'     => 0,
                     'maxfiles'     => EDITOR_UNLIMITED_FILES, // = -1
                     'noclean'      => 1,
                     'return_types' => $types,
                     'subdirs'      => 1,
                     'trusttext'    => 0);
    }

    /**
     * text_editor_options
     *
     * @param xxx $context
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function text_editor_options($context)  {
        return array('subdirs'   => 1,
                     'maxbytes'  => 0,
                     'maxfiles'  => EDITOR_UNLIMITED_FILES,
                     'changeformat' => 1,
                     'context'   => $context,
                     'noclean'   => 1,
                     'trusttext' => 0);
    }

    /**
     * text_page_types
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function text_page_types() {
        return array('entry', 'exit');
    }

    /**
     * filearea_types
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function filearea_types() {
        return array('source', 'config');
    }

    /**
     * text_page_options
     *
     * @param xxx $type
     * @param xxx $subtype (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function text_page_options($type, $subtype='')  {
        $options = array();
        switch (true) {

            case ($type=='entry'):
                $options['title']    = self::ENTRYOPTIONS_TITLE;
                $options['grading']  = self::ENTRYOPTIONS_GRADING;
                $options['dates']    = self::ENTRYOPTIONS_DATES;
                $options['attempts'] = self::ENTRYOPTIONS_ATTEMPTS;
                break;

            case ($type=='exit'):
                if ($subtype=='' || $subtype=='feedback') {
                    $options['title']          = self::ENTRYOPTIONS_TITLE;
                    $options['encouragement']  = self::EXITOPTIONS_ENCOURAGEMENT;
                    $options['attemptscore']   = self::EXITOPTIONS_ATTEMPTSCORE;
                    $options['taskchaingrade'] = self::EXITOPTIONS_TASKCHAINGRADE;
                }
                if ($subtype=='' || $subtype=='links') {
                    $options['retry']  = self::EXITOPTIONS_RETRY;
                    $options['index']  = self::EXITOPTIONS_INDEX;
                    $options['course'] = self::EXITOPTIONS_COURSE;
                    $options['grades'] = self::EXITOPTIONS_GRADES;
                }
                break;
        }
        return $options;
    }

    /**
     * window_options
     *
     * @param string $type 'yesno', 'moodle' or 'numeric'
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function window_options($type='') {
        $options = array();
        if ($type=='' || $type=='moodle') {
            array_push($options, 'moodleheader','moodlenavbar','moodlefooter','moodlebutton');
        }
        if ($type=='' || $type=='yesno') {
            array_push($options, 'resizable', 'scrollbars', 'directories', 'location', 'menubar', 'toolbar', 'status');
        }
        if ($type=='' || $type=='numeric') {
            array_push($options, 'width', 'height');
        }
        return $options;
    }

    /**
     * user_preferences_fieldnames_chain
     *
     * @return array of user_preferences for a TaskChain chain
     * @todo Finish documenting this function
     */
    static public function user_preferences_fieldnames_chain() {
        return array(
            // fields used only when adding a new TaskChain
            'namesource','entrytextsource','exittextsource','taskchain',

            // source/config files
            'sourcefile','sourcelocation','configfile','configlocation',

            // entry/exit pages
            'entrypage','entryformat','entryoptions',
            'exitpage','exitformat','exitoptions',
            'entrycm','entrygrade','exitcm','exitgrade',

            // display
            'outputformat','navigation','title','stopbutton','stoptext',
            'usefilters','useglossary','usemediafilter','studentfeedback','studentfeedbackurl',

            // access restrictions
            'timeopen','timeclose','timelimit','delay1','delay2','delay3',
            'password','subnet','reviewoptions','attemptlimit',

            // grading and reporting
            'grademethod','gradeweighting','clickreporting','discarddetails'
        );
    }

    /**
     * user_preferences_fieldnames_task
     *
     * @return array of user_preferences for a TaskChain task
     * @todo Finish documenting this function
     */
    static public function user_preferences_fieldnames_task() {
        return array(
            // adding a task
            'namesource','addtype',

            // adding / editing a task
            'sourcelocation','sourcefile','configfile','configlocation',

            // display
            'outputformat','navigation','title','stopbutton','stoptext',
            'usefilters','useglossary','usemediafilter','studentfeedback','studentfeedbackurl',

            // access restrictions
            'timeopen','timeclose','timelimit','delay1','delay2','delay3',
            'password','subnet','allowresume','reviewoptions','attemptlimit',

            // scoring, storing and reports
            'scoremethod','scoreignore','scorelimit','scoreweighting','clickreporting','discarddetails',

            // conditions
            'preconditions','postconditions'
        );
    }

    /**
     * get_question_text
     *
     * @uses $DB
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function get_question_text($question)   {
        global $DB;

        if (empty($question->text)) {
            // JMatch, JMix and JQuiz
            return $question->name;
        } else {
            // JCloze and JCross
            return $DB->get_field('taskchain_strings', 'string', array('id' => $question->text));
        }
    }

    /**
     * string_ids
     *
     * @param xxx $field_value
     * @param xxx $max_field_length (optional, default=255)
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function string_ids($field_value, $max_field_length=255)  {
        $ids = array();

        $strings = explode(',', $field_value);
        foreach($strings as $str) {
            if ($id = self::string_id($str)) {
                $ids[] = $id;
            }
        }
        $ids = implode(',', $ids);

        // we have to make sure that the list of $ids is no longer
        // than the maximum allowable length for this field
        if (strlen($ids) > $max_field_length) {

            // truncate $ids just before last comma in allowable field length
            // Note: largest possible id is something like 9223372036854775808
            //       so we must leave space for that in the $ids string
            $ids = substr($ids, 0, $max_field_length - 20);
            $ids = substr($ids, 0, strrpos($ids, ','));

            // create single $str(ing) containing all $strings not included in $ids
            $str = implode(',', array_slice($strings, substr_count($ids, ',') + 1));

            // append the id of the string containing all the strings not yet in $ids
            if ($id = self::string_id($str)) {
                $ids .= ','.$id;
            }
        }

        // return comma separated list of string $ids
        return $ids;
    }

    /**
     * string_id
     *
     * @uses $DB
     * @param xxx $str
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function string_id($str)  {
        global $DB;

        if (! isset($str) || ! is_string($str) || trim($str)=='') {
            // invalid input string
            return false;
        }

        // create md5 key
        $md5key = md5($str);

        if ($id = $DB->get_field('taskchain_strings', 'id', array('md5key'=>$md5key))) {
            // string already exists
            return $id;
        }

        // create a new string record
        $record = (object)array('string'=>$str, 'md5key'=>$md5key);
        if (! $id = $DB->insert_record('taskchain_strings', $record)) {
            throw new moodle_exception('error_insertrecord', 'taskchain', '', 'taskchain_strings');
        }

        // new string was successfully added
        return $id;
    }

    /**
     * Returns the localized description of the attempt status
     *
     * @param xxx $status
     * @return string
     * @todo Finish documenting this function
     */
    static public function format_status($status) {
        $options = self::available_statuses_list();
        if (array_key_exists($status, $options)) {
            return $options[$status];
        } else {
            return $status; // shouldn't happen
        }
    }

    /**
     * Returns a formatted version of the $time
     *
     * @param in $time the time to format
     * @param string $format time format string
     * @param string $notime return value if $time==0
     * @return string
     */
    static public function format_time($time, $format=null, $notime='&nbsp;') {
        if ($time>0) {
            return format_time($time, $format);
        } else {
            return $notime;
        }
    }

    /**
     * Returns a formatted version of an (attempt) $record's score
     *
     * @param object $record from the Moodle database
     * @param string $noscore return value if $record->score is not set
     * @return string
     */
    static public function format_score($record, $default='&nbsp;') {
        if (isset($record->score)) {
            return $record->score;
        } else {
            return $default;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // TaskChain API                                                              //
    ////////////////////////////////////////////////////////////////////////////////

    // - get_pageid_pageclass()
    // - has_entrypage()
    // - has_exitpage()
    // - get_strings($ids)
    // - get_source()
    // - format_allowresume()
    // - format_grademethod($type='grade')

    // - get_sourcefile()
    // - get_outputformat()
    // - get_attempt_renderer_subtype()
    // - set_preferred_pagelayout($PAGE)
    // - to_stdclass()
    // - get_report_renderer_subtype($mode)

    // - get_report_modes()
    // - get_cm($type)
    // - get_gradeitem($type)

    /**
     * get the pageid and pageclass of the current Moodle page
     *
     * @return array, [0] pageid (e.g. mod-taskchain-view), and [1] pageclass (e.g. mod-taskchain)
     */
    public function get_pageid_pageclass() {
        global $SCRIPT;

        if ($this->pageid=='' || $this->pageclass=='') {
            // set pageid and pageclass from $SCRIPT
            // e.g.
            //   $SCRIPT    : /mod/taskchain/view.php
            //   $pageid    : mod-taskchain-view
            //   $pageclass : mod-taskchain

            $str = substr($SCRIPT, 1, -4);
            $str = str_replace('/', '-', $str);
            $pos = strrpos($str, '-');

            $this->pageid = $str;
            $this->pageclass = substr($str, 0, $pos);
        }

        return array($this->pageid, $this->pageclass);
    }

    /**
     * has_entrypage
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function has_entrypage()  {
        return $this->get_field('chain', 'entrypage', 0);
    }

    /**
     * has_exitpage
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function has_exitpage()  {
        return $this->get_field('chain', 'exitpage', 0);
    }

    /**
     * get_strings
     *
     * @uses $DB
     * @param xxx $ids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_strings($ids)  {
        global $DB;
        if (empty($ids)) {
            return array();
        } else {
            list($filter, $params) = $DB->get_in_or_equal($ids);
            return $DB->get_records_select('taskchain_strings', "id $filter", $params, '', 'id,string');
        }
    }

    /**
     * Returns the localized description of the allowresume setting
     *
     * @return string
     */
    public function format_allowresume() {
        $options = $this->available_allowresumes_list();
        if (array_key_exists($this->chain->allowresume, $options)) {
            return $options[$this->chain->allowresume];
        } else {
            return $this->chain->allowresume; // shouldn't happen
        }
    }

    /**
     * Returns the localized description of the grade method
     *
     * @param string $type (optional, default='grade') "grade" or "score"
     * @return string
     */
    public function format_grademethod($type='grade') {
        $options = $this->available_grademethods_list($type);
        if (array_key_exists($this->chain->grademethod, $options)) {
            return $options[$this->chain->grademethod];
        } else {
            return $this->chain->grademethod; // shouldn't happen
        }
    }

    /**
     * Returns the subtype to be used to get a renderer for an attempt at this TaskChain
     *
     * @return string $subtype
     */
    public function get_attempt_subtype() {
        if (empty($this->task)) {
            return '';
        } else {
            return 'attempt_'.$this->task->get_outputformat();
        }
    }

    /**
     * load_class
     *
     * @uses $CFG
     * @param xxx $classname
     * @param xxx $filename
     * @todo Finish documenting this function
     */
    public function load_class($classname, $filename) {
        global $CFG;
        if ($classname) {
            $subdir = '/'.str_replace('_', '/', $classname);
            $subdir = str_replace(array('/mod', '/taskchain'), '', $subdir);
        } else {
            $subdir = '';
        }
        require_once($CFG->dirroot.'/mod/taskchain'.$subdir.'/'.$filename);
    }

    /**
     * set_preferred_pagelayout
     *
     * @param xxx $PAGE
     * @todo Finish documenting this function
     */
    public function set_preferred_pagelayout($PAGE)  {
        // page layouts are defined in theme/xxx/config.php

        if (empty($this->task)) {
            return;
        }

        switch ($this->task->navigation) {

            case self::NAVIGATION_ORIGINAL:
            case self::NAVIGATION_NONE:
                // $PAGE->set_pagelayout('popup');
                $PAGE->set_pagelayout('embedded');
                break;

            case self::NAVIGATION_FRAME:
            case self::NAVIGATION_EMBED:
                $framename = optional_param('framename', '', PARAM_ALPHA);
                if ($framename=='top') {
                    $PAGE->set_pagelayout('frametop');
                }
                if ($framename=='main') {
                    $PAGE->set_pagelayout('embedded');
                }
                break;

            case self::NAVIGATION_TOPBAR:
                $PAGE->set_pagelayout('login'); // no nav menu
                break;
        }
    }

    /**
     * Returns the subtype to be used to get a report renderer for this TaskChain
     *
     * @param string $mode
     * @return string $subtype
     */
    public function get_report_renderer_subtype($mode) {
        if ($mode=='') {
            $mode = 'overview';
        }
        return 'report_'.$mode;
    }

    /**
     * get_report_modes
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_report_modes() {
        $modes = array('overview', 'scores', 'responses', 'analysis');
        if ($this->get_task() && $this->task->clickreporting) {
            $modes[] = 'clicktrail';
        }
        return $modes;
    }

    /**
     * print_error
     *
     * @uses $OUTPUT
     * @param xxx $error
     * @todo Finish documenting this function
     */
    public function print_error($error) {
        global $OUTPUT;
        echo $OUTPUT->header();
        echo $OUTPUT->box($error, 'generalbox', 'notice');
        echo $OUTPUT->footer();
        exit;
    }

    /**
     * force
     *
     * @param xxx $field
     * @param xxx $value (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    protected function force($field, $value=null) {
        $forcefield = 'force'.$field;
        if (is_null($value)) {
            return $this->$forcefield;   // get $field value
        } else {
            $this->$forcefield = $value; // set $field to $value
            return true;
        }
    }

    /**
     * merge_params
     *
     * @param xxx $params (optional, default=false)
     * @param xxx $more_params (optional, default=false)
     * @return xxx
     * @todo Finish documenting this function
     */
    function merge_params($params=false, $more_params=false) {
        $basic_params = array('id'             => 0,
                              'inpopup'        => $this->inpopup,
                              'tab'            => $this->tab,
                              'mode'           => $this->mode,
                              'chainid'        => $this->get_chainid(),
                              'cnumber'        => $this->get_cnumber(),
                              'taskid'         => $this->get_taskid(),
                              'tnumber'        => $this->get_tnumber(),
                              'conditionid'    => $this->get_conditionid(),
                              'conditiontype'  => $this->get_conditiontype(),
                              'chaingradeid'   => $this->get_chaingradeid(),
                              'chainattemptid' => $this->get_chainattemptid(),
                              'taskscoreid'    => $this->get_taskscoreid(),
                              'taskattemptid'  => $this->get_taskattemptid(),
                              'columnlistid'   => $this->get_columnlistid(),
                              'columnlisttype' => $this->get_columnlisttype());
        // remove empty values
        $basic_params = array_filter($basic_params);

        if (! $params) {
            $params = array();
        }
        if (! $more_params) {
            $more_params = array();
        }
        $all_params = array_merge($basic_params, $params, $more_params);

        if (isset($all_params['sesskey']) && empty($all_params['sesskey'])) {
            // sesskey is not required
            unset($all_params['sesskey']);
        } else {
            // sesskey was not set, so set it
            $all_params['sesskey'] = sesskey();
        }

        // remove unnecessary parameters
        $unset = array();
        $unsettask = false;
        $unsetchain = false;
        switch (true) {
            case ! empty($all_params['taskattemptid']):
                $unset[] = 'taskscoreid';
                $unset[] = 'tnumber';
            case ! empty($all_params['taskscoreid']):
                $unset[] = 'chainattemptid';
                $unsettask = true;
            case ! empty($all_params['chainattemptid']):
                $unset[] = 'chaingradeid';
                $unset[] = 'cnumber';
            case ! empty($all_params['chaingradeid']):
                $unsetchain = true;
        }
        switch (true) {
            case ! empty($all_params['conditionid']):
            case $unsettask:
                $unset[] = 'taskid';
            case ! empty($all_params['taskid']):
            case $unsetchain:
                $unset[] = 'chainid';
            case ! empty($all_params['chainid']):
                $unset[] = 'taskchainid';
            case ! empty($all_params['taskchainid']):
                $unset[] = 'coursemoduleid';
            case ! empty($all_params['coursemoduleid']):
                $unset[] = 'courseid';
        }

        foreach ($unset as $name) {
            unset($all_params[$name]);
        }

        return $all_params;
    }

    /**
     * force_cnumber
     *
     * @param xxx $value (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function force_cnumber($value=null) {
        return $this->force('cnumber', $value);
    }

    /**
     * force_tnumber
     *
     * @param xxx $value (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function force_tnumber($value=null) {
        return $this->force('tnumber', $value);
    }

    /**
     * force_taskid
     *
     * @param xxx $value (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function force_taskid($value=null) {
        return $this->force('taskid', $value);
    }

    // IF this user has [attemptcount] [attempttype] attempts at [conditiontask]
    //     AND each score is greater than [conditionscore<0] OR each score is no more than [conditionscore>0]
    //     AND duration of attempts greater than [attemptduration<0] OR duration of attempts is no more than [attemptduration>0]
    //     AND delay since attempts is greater than [attemptdelay<0] OR delay somce attempts is no more than [attemptdelay>0]
    // THEN condition is satisfied
    // ELSE condition is *not* satisfied
    //
    // All preconditions must be satisfied(i.e. AND)
    // Post condition with highest score is used (i.e. OR)
    //
    // Additionally, a postcondition is not satisfied if the preconditions for [nexttaskid] are not satisfied

    /**
     * check_conditions
     *
     * @uses $DB
     * @param xxx $conditiontype
     * @param xxx $taskid
     * @param xxx $previoustaskid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_conditions($conditiontype, $taskid, $previoustaskid) {
        global $DB;

        // set initial return value
        if ($conditiontype==taskchain::CONDITIONTYPE_PRE) {
            $ok = true; // if there are no pre conditions, then the user can do this task
        } else {
            $ok = false; // if there are no post conditions, then there is no prescribed next task
        }

        switch (true) {
            case $this->chain->allowfreeaccess > 0: // required grade
                if ($this->get_maxchainattemptgrade() >= $this->chain->allowfreeaccess) {
                    return $ok;
                }
                break;
            case $this->chain->allowfreeaccess < 0: // number of completed attempts
                if ($this->get_chaincompleted() >= abs($this->chain->allowfreeaccess)) {
                    return $ok;
                }
                break;
        }

        if (! $conditions = $this->get_conditions($conditiontype, $taskid)) {
            // no conditions found for this task
            return $ok;
        }

        // make sure we have info on all tasks
        if (is_null($this->tasks)) {
            $this->get_tasks();
        }

        // initialize sortorder
        $sortorder = -1;
        // Note: $condition->sortorder is always >=0 because
        // the sortorder field in the database is UNSIGNED

        foreach ($conditions as $condition) {

            if ($sortorder>=0) {
                // not the first condition, so check status of previous group of conditions
                if ($ok) {
                    if ($conditiontype==taskchain::CONDITIONTYPE_POST) {
                        // previous post-condition was satisfied (=success!)
                        break;
                    } else if ($condition->sortorder != $sortorder) {
                        // previous group of pre-conditions were satisfied (=success!)
                        break;
                    }
                } else if ($condition->sortorder==$sortorder && $conditiontype==taskchain::CONDITIONTYPE_PRE) {
                    // a previous pre-condition was not satisfied (=failure),
                    // so skip remaining pre-conditions with the same sortorder
                    continue;
                }
            }
            $sortorder = $condition->sortorder;

            // this task has pre/post conditions, so the default return value is FALSE
            //     for pre-conditions, this means the task cannot be done
            //     for post-conditions, this means no next task was specified
            // to return TRUE, we must find a condition that is satisfied
            $ok = false;

            switch ($condition->conditiontaskid) {

                case taskchain::CONDITIONTASKID_SAME:
                    $conditiontaskid = $taskid;
                    break;

                case taskchain::CONDITIONTASKID_PREVIOUS:
                    $conditiontaskid = $previoustaskid;
                    break;

                default:
                    // specific task id
                    $conditiontaskid = $condition->conditiontaskid;
            }

            if (! isset($this->tasks[$conditiontaskid])) {
                // condition task id is not valid !!
                continue;
            }

            if (! $this->get_cache_taskattempts($conditiontaskid)) {
                // no attempts at the [conditiontask], so condition cannot be satisifed
                continue;
            }

            $usort = &$this->cache_taskattemptsusort[$conditiontaskid];
            $attempts = &$this->cache_taskattempts[$conditiontaskid];

            if ($conditiontype==taskchain::CONDITIONTYPE_PRE && $condition->attemptdelay) {
                // sort attempts by time DESC (most recent attempts first)
                $this->usort_attempts($attempts, $usort, 'time_desc');

                // get delay (=time elapsed) since most recent attempt
                $attempt = reset($attempts);
                $attemptdelay = ($this->time - $attempt->timemodified);

                if ($condition->attemptdelay<0 && $attemptdelay<$condition->attemptdelay) {
                    // not enough time elapsed, so precondition fails
                    return false;
                }
                if ($condition->attemptdelay>0 && $attemptdelay>$condition->attemptdelay) {
                    // too much time has elapsed, so precondition fails
                    return false;
                }
            }

            $attemptcount = 0;
            $attemptduration = 0;
            switch ($condition->attempttype) {

                case taskchain::ATTEMPTTYPE_ANY:

                    if ($condition->attemptduration>0) {
                        // total time must not exceed attemptduration, so
                        // sort attempts by duration ASC (fastest attempts first)
                        $this->usort_attempts($attempts, $usort, 'duration_asc');
                    }
                    if ($condition->attemptduration<0) {
                        // total time must be at least attemptduration, so
                        // sort attempts by duration DESC (slowest attempts first)
                        $this->usort_attempts($attempts, $usort, 'duration_desc');
                    }
                    foreach ($attempts as $attempt) {
                        $ok = $this->check_condition_score($condition, $attempt->score);
                        if (! $ok) {
                            // score condition not satisfied
                            continue;
                        }

                        $attemptduration += $attempt->duration;
                        $attemptcount ++;

                        $ok = $this->check_condition_max($condition, $attemptcount, $attemptduration);
                        if (! $ok) {
                            // exceeded maximum time or attempt count (=> the condition has failed)
                            break;
                        }

                        $ok = $this->check_condition_min($condition, $attemptcount, $attemptduration);
                        if ($ok) {
                            // minimum time and count conditions satisfied (=> success!)
                            break;
                        }
                    }
                    break;

                case taskchain::ATTEMPTTYPE_RECENT:

                    // sort attempts by time DESC (recent attempts first)
                    $this->usort_attempts($attempts, $usort, 'time_desc');

                    foreach ($attempts as $attempt) {
                        $ok = $this->check_condition_score($condition, $attempt->score);
                        if (! $ok) {
                            break;
                        }
                        $attemptduration += $attempt->duration;
                        $attemptcount ++;

                        $ok = $this->check_condition_max($condition, $attemptcount, $attemptduration);
                        if (! $ok) {
                            // exceeded maximum time or attempt count (=> the condition has failed)
                            break;
                        }

                        $ok = $this->check_condition_min($condition, $attemptcount, $attemptduration);
                        if ($ok) {
                            // minimum time and count conditions satisfied (=>success!)
                            break;
                        }
                    }
                    break;

                case taskchain::ATTEMPTTYPE_CONSECUTIVE:

                    // sort attempts by time DESC
                    foreach ($attempts as $attempt) {
                        $ok = $this->check_condition_score($condition, $attempt->score);
                        if ($ok) {
                            $attemptduration += $attempt->duration;
                            $attemptcount ++;
                        } else {
                            // reset totals (but keep looping through attempts)
                            $attemptcount = 0;
                            $attemptduration = 0;
                        }

                        $ok = $this->check_condition_max($condition, $attemptcount, $attemptduration);
                        if (! $ok) {
                            // exceeded maximum time or attempt count (=> the condition has failed)
                            break;
                        }

                        $ok = $this->check_condition_min($condition, $attemptcount, $attemptduration);
                        if ($ok) {
                            // minimum time and count conditions satisfied (=>success!)
                            break;
                        }
                    }
                    break;

            } // end switch ($condition->attempttype)

            if ($conditiontype==taskchain::CONDITIONTYPE_POST && $ok) {
                // this postcondition has been satisfied, so get nexttaskid (or false, if there isn't one)
                $ok = $this->get_nexttaskid($condition, $taskid, $previoustaskid);
            }
        } // end foreach $conditions

        return $ok;
    } // end function : check_conditions

    /**
     * check_condition_score
     *
     * @param xxx $condition (passed by reference)
     * @param xxx $attemptscore (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_condition_score(&$condition, &$attemptscore) {
        if ($condition->conditionscore>0 && $attemptscore > $condition->conditionscore) {
            // maximum score exceeded
            return false;
        }
        if ($condition->conditionscore<0 && $attemptscore < $condition->abs_conditionscore) {
            // minimum score not reached
            return false;
        }
        // score condition is satisfied
        return true;
    }

    /**
     * check_condition_max
     *
     * @param xxx $condition (passed by reference)
     * @param xxx $attemptcount
     * @param xxx $attemptduration
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_condition_max(&$condition, $attemptcount, $attemptduration) {
        if ($condition->attemptcount>0 && $attemptcount > $condition->attemptcount) {
            // maximum number of attempts exceeded
            return false;
        }
        if ($condition->attemptduration>0 && $attemptduration > $condition->attemptduration) {
            // maximum time exceeded
            return false;
        }
        // "max" conditions are satisfied
        return true;
    }

    /**
     * check_condition_min
     *
     * @param xxx $condition (passed by reference)
     * @param xxx $attemptcount
     * @param xxx $attemptduration
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_condition_min(&$condition, $attemptcount, $attemptduration) {
        if ($condition->attemptcount<0 && $attemptcount < $condition->abs_attemptcount) {
            // minimum number of attempts not reached
            return false;
        }
        if ($condition->attemptduration<0 && $attemptduration < $condition->abs_attemptduration) {
            // minimum time not reached
            return false;
        }
        // "min" conditions are satisfied
        return true;
    }

    /**
     * usort_attempts
     *
     * @param xxx $attempts (passed by reference)
     * @param xxx $usort (passed by reference)
     * @param xxx $newusort
     * @todo Finish documenting this function
     */
    public function usort_attempts(&$attempts, &$usort, $newusort) {
        if ($usort == $newusort) {
            // do nothing - attempts are already in order
        } else {
            $usort = $newusort;
            // "uasort" maintains the id => record correlation (where "usort" does not)
            uasort($attempts, 'taskchain_usort_'.$usort);
        }
    }

    /**
     * get_nexttaskid
     *
     * @uses $DB
     * @param xxx $condition (passed by reference)
     * @param xxx $taskid
     * @param xxx $previoustaskid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_nexttaskid(&$condition, $taskid, $previoustaskid) {
        global $DB;

        // initialize recturn value
        $nexttaskid = 0;

        $ids = array();
        $sql = '';
        $skip = 0;
        $random = false;

        switch ($condition->nexttaskid) {

            case taskchain::CONDITIONTASKID_SAME:
                $ids[] = $taskid;
                break;

            case taskchain::CONDITIONTASKID_PREVIOUS:
                $sql = "
                    SELECT id, sortorder
                    FROM {taskchain_tasks}
                    WHERE chainid=$this->chainid AND sortorder<".$this->tasks[$taskid]->sortorder."
                    ORDER BY sortorder DESC
                ";
                break;

            case taskchain::CONDITIONTASKID_NEXT1:
            case taskchain::CONDITIONTASKID_NEXT2:
            case taskchain::CONDITIONTASKID_NEXT3:
            case taskchain::CONDITIONTASKID_NEXT4:
            case taskchain::CONDITIONTASKID_NEXT5:
                // skip is the number of tasks to skip (next1=0, next2=1, etc)
                // remember nexttaskid and the NEXT constants are all negative
                $skip = (taskchain::CONDITIONTASKID_NEXT1 - $condition->nexttaskid);
                $sql = "
                    SELECT id, sortorder
                    FROM {taskchain_tasks}
                    WHERE chainid=$this->chainid AND sortorder>".$this->tasks[$taskid]->sortorder."
                    ORDER BY sortorder ASC
                ";
                break;

            case taskchain::CONDITIONTASKID_UNSEEN: // no attempts
                $sql = "
                    SELECT id, sortorder FROM {taskchain_tasks} q
                    LEFT JOIN (
                        # ids of tasks attempted by this user (in this chain attempt)
                        SELECT DISTINCT taskid FROM {taskchain_task_attempts}
                        WHERE taskid IN (
                            # tasks in this chain
                            SELECT id FROM {taskchain_tasks} WHERE chainid=$this->chainid
                        ) AND cnumber=$this->cnumber AND userid=$this->userid
                    ) a ON q.id=a.taskid
                    WHERE chainid=$this->chainid AND a.taskid IS NULL
                    ORDER BY sortorder ASC
                ";
                $random = true;
                break;

            case taskchain::CONDITIONTASKID_UNANSWERED: // no responses
                $sql = "
                    SELECT id, sortorder FROM {taskchain_tasks} q
                    LEFT JOIN (
                        # tasks with attempts that have responses
                        SELECT DISTINCT taskid FROM {taskchain_task_attempts}
                        WHERE id IN (
                            # attempts that have responses
                            SELECT DISTINCT attemptid FROM {taskchain_responses}
                            WHERE attemptid IN (
                                # attempts (on tasks in this chain)
                                SELECT id FROM {taskchain_task_attempts}
                                WHERE taskid IN (
                                    # tasks in this chain
                                    SELECT id FROM {taskchain_tasks} WHERE chainid=$this->chainid
                                ) AND cnumber=$this->cnumber AND userid=$this->userid
                            )
                        )
                    ) a ON q.id=a.taskid
                    WHERE chainid=$this->chainid AND a.taskid IS NULL
                    ORDER BY q.sortorder ASC
                ";
                $random = true;
                break;

            case taskchain::CONDITIONTASKID_INCORRECT: // score < 100%
                $sql = "
                    SELECT q.id, q.sortorder FROM {taskchain_tasks} q
                    LEFT JOIN (
                        SELECT DISTINCT taskid FROM {taskchain_task_scores}
                        WHERE taskid IN (
                            # tasks in this chain
                            SELECT id FROM {taskchain_tasks} WHERE chainid=$this->chainid
                        ) AND score=100 AND cnumber=$this->cnumber AND userid=$this->userid
                    ) qs ON q.id=qs.taskid
                    WHERE chainid=$this->chainid AND qs.taskid IS NULL
                ";
                $random = true;
                break;

            case taskchain::CONDITIONTASKID_RANDOM:
                $sql = "
                    SELECT q.id, q.sortorder
                    FROM {taskchain_tasks} q
                    WHERE q.chainid=$this->chainid
                    ORDER BY sortorder ASC
                ";
                $random = true;
                break;

            case taskchain::CONDITIONTASKID_MENUALL:
            case taskchain::CONDITIONTASKID_MENUNEXT:
                $nexttaskid = $condition->nexttaskid;
                break;

            default:
                $ids[] = $condition->nexttaskid;
                break;
        } // end switch : $condition->nexttaskid

        if ($sql) {
            if ($records = $DB->get_records_sql($sql)) {
                $ids = array_keys($records);
            }
        }

        if ($i_max = count($ids)) {

            // set capability, if necessary
            static $has_capability_preview = null;
            if (is_null($has_capability_preview)) {
                $has_capability_preview = has_capability('mod/taskchain:preview', $this->modulecontext);
            }

            $i = 0;
            while ($i<$i_max) {
                if ($random) {
                    $i = $this->random_number(0, $i_max-1);
                }
                if ($has_capability_preview) {
                    // a teacher: don't check pre-conditions
                    $ok = true;
                } else {
                    // a student: always check pre-conditions on candidate for next task
                    // (and pass the current $taskid to be the $previoustaskid in order to do the checking)
                    $ok = $this->check_conditions(taskchain::CONDITIONTYPE_PRE, $ids[$i], $taskid);
                }
                if ($ok) {
                    if ($skip > 0) {
                        $skip--;
                    } else {
                        $nexttaskid = $ids[$i];
                        break; // nexttaskid has been found
                    }
                }
                if ($random) {
                    // remove this id from the $ids array
                    $ids = array_splice($ids, $i, 1);
                    $i_max--;
                    $i = 0;
                } else {
                    $i++;
                }
            }
        }

        return $nexttaskid;
    }

    /**
     * random_number
     *
     * @param xxx $min (optional, default=0)
     * @param xxx $max (optional, default=RAND_MAX)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function random_number($min=0, $max=RAND_MAX) {
        static $rand;
        if (! isset($rand)) {
            // get random number functons ("mt" functions are available starting PHP 4.2)
            $rand = function_exists('mt_rand') ? 'mt_rand' : 'rand';
            $srand = function_exists('mt_srand') ? 'mt_srand' : 'srand';

            // seed the random number generator
            list($usec, $sec) = explode(' ', microtime());
            $srand((float) $sec + ((float) $usec * 100000));
        }
        return $rand($min, $max);
    }

    /**
     * delete_selected_attempts
     *
     * @uses $DB
     * @param xxx $status (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function delete_selected_attempts(&$selected, $status=0) {
        global $DB;

        if (! $userfilter = $this->get_userfilter('')) {
            return false; // no users selected
        }

        //if (! $selected = optional_param('selected', 0, PARAM_INT)) {
        //    return false; // no attempts select
        //}

        //if (! $confirmed = optional_param('confirmed', 0, PARAM_INT)) {
        //    return false; // delete is not confirmed
        //}

        // we are going to return some totals of how many records were deleted
        $this->deleted = (object)array(
            'taskattempts'=>0, 'taskscores'=>0, 'chainattempts'=>0, 'chaingrades'=>0, 'total'=>0
        );

        list($taskchains, $chains, $tasks, $taskattempts) = $this->clean_selected($selected, 'deleteattempts');

        if (count($taskchains)) {
            $parentfilter = 'parentid IN ('.implode(',', array_keys($taskchains)).')';
        } else {
            $parentfilter = 'parentid IN (SELECT id FROM {taskchain} WHERE course='.$this->course->id.')';
        }
        if (count($chains)) {
            $chainfilter = 'chainid IN ('.implode(',', array_keys($chains)).')';
        } else {
            $chainfilter = 'chainid IN (SELECT id FROM {taskchain_chains}  WHERE parenttype=0 AND '.$parentfilter.')';
        }
        if (count($tasks)) {
            $taskfilter = 'taskid IN ('.implode(',', array_keys($tasks)).')';
        } else {
            $taskfilter = 'taskid IN (SELECT id FROM {taskchain_tasks} WHERE '.$chainfilter.')';
        }

        $select = $this->get_selected_sql($selected, $chains, $tasks, $taskattempts);

        if ($select) {
            $select = $userfilter.' AND '. $select;
            if ($status) {
                $select .= " AND status=$status";
            }
           // remove all task_attempts by users in $userfilter
            if ($records = $DB->get_records_select('taskchain_task_attempts', $select, null, 'id', 'id')) {
                $select = 'id IN ('.implode(',', array_keys($records)).')';
                if (! $DB->delete_records_select('taskchain_task_attempts', $select)) {
                    print_error('error_deleterecords', 'taskchain', 'taskchain_task_attempts');
                }
                // update totals
                $this->deleted->taskattempts = count($records);
                $this->deleted->total += $this->deleted->taskattempts;
            }
        }

        // remove all task_scores which have no task attempts by users in $userfilter
        $select = "id IN (
            SELECT qs.id FROM {taskchain_task_scores} qs
            LEFT JOIN (
                SELECT id,taskid,cnumber,userid,score FROM {taskchain_task_attempts}
                WHERE $userfilter AND $taskfilter
            ) qa ON qs.taskid=qa.taskid AND qs.cnumber=qa.cnumber AND qs.userid=qa.userid
            WHERE qs.$userfilter AND qs.$taskfilter AND qa.score IS NULL
        )";

        if ($records = $DB->get_records_select('taskchain_task_scores', $select, null, 'id', 'id')) {
            $select = 'id IN ('.implode(',', array_keys($records)).')';
            if (! $DB->delete_records_select('taskchain_task_scores', $select)) {
                print_error('error_deleterecords', 'taskchain', 'taskchain_task_scores');
            }
            // update totals
            $this->deleted->taskscores = count($records);
            $this->deleted->total += $this->deleted->taskscores;
        }

        // remove all chain_attempts which have no task scores by users in $userfilter
        $select = "id IN (
            SELECT ua.id FROM {taskchain_chain_attempts} ua
            LEFT JOIN (
                SELECT taskid,cnumber,userid,q.chainid,score FROM {taskchain_task_scores}
                LEFT JOIN (
                    SELECT id,chainid FROM {taskchain_tasks} WHERE $chainfilter
                ) q ON taskid=q.id
                WHERE $userfilter AND $taskfilter
            ) qs ON ua.chainid=qs.chainid AND ua.cnumber=qs.cnumber AND ua.userid=qs.userid
            WHERE ua.$userfilter AND ua.$chainfilter AND qs.score IS NULL
        )";

        if ($records = $DB->get_records_select('taskchain_chain_attempts', $select, null, 'id', 'id')) {
            $select = 'id IN ('.implode(',', array_keys($records)).')';
            if (! $DB->delete_records_select('taskchain_chain_attempts', $select)) {
                print_error('error_deleterecords', 'taskchain', 'taskchain_chain_attempts');
            }
            $this->deleted->chainattempts = count($records);
            $this->deleted->total += $this->deleted->chainattempts;
        }

        // remove all chain_grades which have no chain_attempts by users in $userfilter
        $select = "id IN (
            SELECT ug.id FROM {taskchain_chain_grades} ug
            LEFT JOIN (
                SELECT userid,chainid,u.parenttype,u.parentid,grade FROM {taskchain_chain_attempts}
                LEFT JOIN (
                    SELECT id,parenttype,parentid FROM {taskchain_chains}
                    WHERE parenttype=0 AND $parentfilter
                ) u ON chainid=u.id
                WHERE $userfilter AND $chainfilter
            ) ua ON ug.parenttype=ua.parenttype AND ug.parentid=ua.parentid AND ug.userid=ua.userid
            WHERE ug.$userfilter AND ug.parenttype=0 AND ug.$parentfilter AND ua.grade IS NULL
        )";

        if ($records = $DB->get_records_select('taskchain_chain_grades', $select, null, 'id', 'id')) {
            $select = 'id IN ('.implode(',', array_keys($records)).')';
            if (! $DB->delete_records_select('taskchain_chain_grades', $select)) {
                print_error('error_deleterecords', 'taskchain', 'taskchain_chain_grades');
            }
            $this->deleted->chaingrades = count($records);
            $this->deleted->total += $this->deleted->chaingrades;
        }

        // regrade tasks, chains and taskchains
        $this->regrade->selected_tasks($selected, $taskchains, $chains, $tasks, $userfilter);

        // Note: don't use $this->regrade_selected_tasks(), as this causes the following error:
        // Parameter 1 to taskchain_regrade::selected_tasks() expected to be a reference, value given

        return $this->deleted;
    }

    /**
     * get_selected_sql
     *
     * @param xxx $selected (passed by reference)
     * @param xxx $chains (passed by reference)
     * @param xxx $tasks (passed by reference)
     * @param xxx $taskattempts (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_selected_sql(&$selected, &$chains, &$tasks, &$taskattempts) {
        $chainid_filters = array();
        foreach ($selected as $chainid => $cnumbers) {
            if (! $chainid) {
                continue;
            }
            if (empty($chains[$chainid])) {
                unset($selected[$chainid]);
                continue; // invalid $chainid - shouldn't happen
            }
            $chainid_filter = array();
            // the chainid filter to select taskids for this chainid will be added later
            // because it is only required if specific taskids have not been specified
            $add_chainid_filter = true;
            if (is_array($cnumbers)) {
                $cnumber_filters = array();
                foreach ($cnumbers as $cnumber => $cnumberdetails) {
                    $cnumber_filter = array();
                    if ($cnumber) {
                        $cnumber_filter[] = "cnumber=$cnumber";
                    }
                    if (is_array($cnumberdetails)) {
                        $taskid_filters = array();
                        foreach ($cnumberdetails as $taskid => $tnumbers) {
                            if (! $taskid) {
                                continue;
                            }
                            if (empty($tasks[$taskid])) {
                                unset($selected[$chainid][$cnumber][$taskid]);
                                continue; // invalid $taskid - shouldn't happen
                            }
                            $taskid_filter = array();
                            $taskid_filter[] = "taskid=$taskid";
                            $add_chainid_filter = false; // not required if we have a taskid
                            if (is_array($tnumbers)) {
                                $tnumber_filters = array();
                                foreach ($tnumbers as $tnumber => $tnumberdetails) {
                                    $tnumber_filter = array();
                                    if ($tnumber) {
                                        $tnumber_filter[] = "tnumber=$tnumber";
                                    }
                                    if (is_array($tnumberdetails)) {
                                        $id_filters = array();
                                        foreach ($tnumberdetails as $taskattemptid => $delete) {
                                            if (! $taskattemptid) {
                                                continue;
                                            }
                                            if (empty($taskattempts[$taskattemptid])) {
                                                unset($selected[$chainid][$cnumber][$taskid][$tnumber][$taskattemptid]);
                                                continue; // invalid task attempt id - shouldn't happen
                                            }
                                            $id_filters[] = "id=$taskattemptid";
                                        }
                                       switch (count($id_filters)) {
                                            case 0: break;
                                            case 1: $tnumber_filter[] = 'id='.$id_filters[0]; break;
                                            default: $tnumber_filter[] = 'id IN ('.implode(',', $id_filters).')';
                                        }
                                    }
                                    switch (count($tnumber_filter)) {
                                        case 0: break;
                                        case 1: $tnumber_filters[] = $tnumber_filter[0]; break;
                                        default: $tnumber_filters[] = '('.implode(' AND ', $tnumber_filter).')';
                                    }
                                }
                                switch (count($tnumber_filters)) {
                                    case 0: break;
                                    case 1: $taskid_filter[] = $tnumber_filters[0]; break;
                                    default: $taskid_filter[] = '('.implode(' OR ', $tnumber_filters).')';
                                }
                            }

                            switch (count($taskid_filter)) {
                                case 0: break;
                                case 1: $taskid_filters[] = $taskid_filter[0]; break;
                                default: $taskid_filters[] = '('.implode(' AND ', $taskid_filter).')';
                            }
                        }
                        switch (count($taskid_filters)) {
                            case 0: break;
                            case 1: $cnumber_filter[] = $taskid_filters[0];break;
                            default: $cnumber_filter[] = '('.implode(' OR ', $taskid_filters).')';
                        }
                    }
                    switch (count($cnumber_filter)) {
                        case 0: break;
                        case 1: $cnumber_filters[] = $cnumber_filter[0]; break;
                        default: $cnumber_filters[] = '('.implode(' AND ', $cnumber_filter).')';
                    }
                }
                switch (count($cnumber_filters)) {
                    case 0: break;
                    case 1: $chainid_filter[] = $cnumber_filters[0]; break;
                    default: $chainid_filter[] = '('.implode(' OR ', $cnumber_filters).')';
                }
            }
            if ($add_chainid_filter) {
                // prepend filter to select only taskids for this chainid
                array_unshift($chainid_filter, "taskid IN (SELECT id FROM {taskchain_tasks} WHERE chainid=$chainid)");
            }
            switch (count($chainid_filter)) {
                case 0: break;
                case 1: $chainid_filters[] = $chainid_filter[0]; break;
                default: $chainid_filters[] = '('.implode(' AND ', $chainid_filter).')';
            }
        }
        switch (count($chainid_filters)) {
            case 0: return ''; // nothing to delete
            case 1: return $chainid_filters[0];
            default: return '('.implode(' OR ', $chainid_filters).')';
        }
    }

    /**
     * clean_selected
     *
     * @uses $CFG
     * @uses $DB
     * @param xxx $selected (passed by reference)
     * @param xxx $capability
     * @return xxx
     * @todo Finish documenting this function
     */
    public function clean_selected(&$selected, $capability) {
        // we are expecting the "selected" array to be something like this:
        //     selected[chainid][cnumber][taskid][tnumber][taskattemptid]
        // cnumber and tnumber maybe zero
        // taskattemptid maybe be missing
        global $CFG, $DB;

        // arrays to hold ids of records this user wants to delete
        $coursemodules = array(); // course_modules (Moodle 1.6 only)
        $taskattemptids = array(); // taskchain_task_attempts
        $taskids = array(); // taskchain_tasks
        $chainids = array(); // taskchain_chains
        $taskchainids = array(); // taskchain

        // get ids of records this user wants to delete (tidy up $selected where necessary)
        foreach ($selected as $chainid => $cnumbers) {
            if (! $cnumbers) {
                unset($selected[$chainid]);
                continue;
            }
            if (! $chainid) {
                // you could add ids for all chains in this course here, but it is not necessary
                unset($selected[$chainid]);
                continue;
            }
            $chainids[] = $chainid;
            if (is_array($cnumbers)) {
                foreach ($cnumbers as $cnumber => $cnumberdetails) {
                    if (! $cnumberdetails) {
                        unset($selected[$chainid][$cnumber]);
                        continue;
                    }
                    if (is_array($cnumberdetails)) {
                        foreach ($cnumberdetails as $taskid => $tnumbers) {
                            if (! $tnumbers) {
                                unset($selected[$chainid][$cnumber][$taskid]);
                                continue;
                            }
                            if (! $taskid) {
                                // you could add ids for all tasks in this chain here, but it is not necessary
                                unset($selected[$chainid][$cnumber][$taskid]);
                                continue;
                            }
                            if (is_array($tnumbers)) {
                                $taskids[] = $taskid;
                                foreach ($tnumbers as $tnumber => $tnumberdetails) {
                                    if (! $tnumberdetails) {
                                        unset($selected[$chainid][$cnumber][$taskid][$tnumber]);
                                        continue;
                                    }
                                    if (is_array($tnumberdetails)) {
                                        foreach ($tnumberdetails as $taskattemptid => $delete) {
                                            if (! $delete) {
                                                unset($selected[$chainid][$cnumber][$taskid][$tnumber][$taskattemptid]);
                                                continue;
                                            }
                                            $taskattemptids[] = $taskattemptid;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } // end foreach ($selected)

        // get requested task attempts
        if (empty($taskattemptids)) {
            $taskattempts = array();
        } else {
            $fields = 'id,taskid';
            $select = 'id IN ('.implode(',', array_unique($taskattemptids)).')';
            if (! $taskattempts = $DB->get_records_select('taskchain_task_attempts', $select, null, 'id', $fields)) {
                $taskattempts = array();
            }
            // extract task ids from requested task attempts
            foreach ($taskattempts as $id=>$taskattempt) {
                $taskids[] = $taskattempt->taskid;
            }
        }

        // get requested tasks
        if (empty($taskids)) {
            $tasks = array();
        } else {
            $fields = 'id,chainid,timelimit,allowresume,attemptlimit,scoremethod,scoreignore,scorelimit,scoreweighting';
            $select = 'id IN ('.implode(',', array_unique($taskids)).')';
            if (! $tasks = $DB->get_records_select('taskchain_tasks', $select, null, 'id', $fields)) {
                $tasks = array();
            }
            // extract chain ids from requested tasks
            foreach ($tasks as $taskid => $task) {
                $chainids[] = $task->chainid;
            }
        }

        // get requested chains and taskchainids
        if (empty($chainids)) {
            $chains = array();
        } else {
            $fields = 'id,parenttype,parentid,timelimit,allowresume,attemptlimit,attemptgrademethod,grademethod,gradeignore,gradelimit,gradeweighting';
            $select = 'id IN ('.implode(',', array_unique($chainids)).') AND parenttype='.self::PARENTTYPE_ACTIVITY;
            if (! $chains = $DB->get_records_select('taskchain_chains', $select, null, 'id', $fields)) {
                $chains = array();
            }
            // extract taskchain ids from requested chains
            foreach ($chains as $chainid=>$chain) {
                $taskchainids[] = $chain->parentid;
            }
        }

        // select requested course modules for which this user is allowed to delete attempts at TaskChains
        if (count($taskchainids)) {

            if ($modinfo = unserialize($this->course->modinfo)) {
                foreach ($modinfo as $cmid => $mod) {
                    // Note: $mod->id is the taskchain id, $mod->cm is the course_modules id
                    if ($mod->mod=='taskchain') {
                        $taskchainid = $mod->id;
                        if (in_array($taskchainid, $taskchainids) && $this->can->$capability(false, $this->context(CONTEXT_MODULE, $cmid))) {
                            // user can delete attempts, so save the taskchain id
                            // we don't need to get the full taskchain/coursemodule record
                            $coursemodules[$cmid] = true;
                            $taskchains[$taskchainid] = (object)array(
                                // these fields are required by taskchain_grade_item_update() in "mod/taskchain/lib.php"
                                'id'=>$taskchainid, 'cmidnumber'=>$cmid, 'course'=>$this->course->id, 'name'=>format_string(urldecode($mod->name))
                            );
                        }
                    }
                }
            }
        }

        // we don't need these anymore
        unset($coursemodules);
        unset($taskchainids);
        unset($chainids);
        unset($taskids);
        unset($taskattemptids);

        // $coursemodules now holds only records for TaskChain activities for which this user has the required $capability
        // $taskchains holds the corresponding taskchain records

        // remove chains that this user is not allowed to touch
        foreach ($chains as $chainid=>$chain) {
            if (empty($taskchains[$chain->parentid])) {
                unset($chains[$chainid]);
            } else {
                $chains[$chainid]->tasks = array();
                $chains[$chainid]->userids = array();

                // transfer gradelimit and gradeweighting to $taskchain
                // (required for taskchain_get_user_grades() in "mod/taskchain/lib.php")
                $taskchains[$chain->parentid]->gradelimit = $chain->gradelimit;
                $taskchains[$chain->parentid]->gradeweighting = $chain->gradeweighting;
            }
        }

        // remove tasks that this user is not allowed to touch
        foreach ($tasks as $taskid=>$task) {
            if (empty($chains[$task->chainid])) {
                unset($tasks[$taskid]);
            }
            $tasks[$taskid]->userids = array();
        }

        // remove task attempts that this user is not allowed to delete
        foreach ($taskattempts as $id=>$taskattempt) {
            if (empty($tasks[$taskattempt->taskid])) {
                unset($taskattempts[$id]);
            }
        }

        return array(&$taskchains, &$chains, &$tasks, &$taskattempts);
    }

    /**
     * count_records_select
     *
     * @param xxx $table
     * @param xxx $select
     * @param xxx array
     * @param xxx $params (optional, default=null)
     * @param xxx $countitem (optional, default="COUNT('x')")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function count_records_select($table, $select, array $params=null, $countitem="COUNT('x')") {
        if ($select) {
            $select = "WHERE $select";
        }
        return $this->count_records_sql("SELECT $countitem FROM {" . $table . "} $select", $params);
    }

    /**
     * count_records_sql
     *
     * @uses $DB
     * @param xxx $sql
     * @param xxx array
     * @param xxx $params (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function count_records_sql($sql, array $params=null) {
        global $DB;
        if ($count = $DB->get_field_sql($sql, $params)) {
            return (int)$count;
        } else {
            return 0;
        }
    }

    /**
     * to_stdclass
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function to_stdclass() {
        $stdclass = parent::to_stdclass();
        // extra fields required for grades
        if (isset($this->course) && is_object($this->course)) {
            $stdclass->course = $this->course->id;
        }
        if (isset($this->cm) && is_object($this->cm)) {
            $stdclass->cmidnumber = $this->cm->id;
        }
        $stdclass->modname = 'taskchain';
        return $stdclass;
    }

    /**
     * sort_tasks
     *
     * @param string $field the name of the field on which tasks are to be sorted
     * @param string $direction "asc" or "desc"
     * @return void, but may alter order of $this->tasks array
     * @todo Finish documenting this function
     */
    public function sort_tasks($field, $direction) {
        $method = 'sort_'.$field.'_'.$direction;
        if (method_exists($this, $method) && $this->get_tasks()) {
            uasort($this->tasks, array($this, $method));
        }
    }

    /**
     * sort_sortorder_asc
     *
     * @param object $a (passed by reference) an object to be compared
     * @param object $b (passed by reference) an object to be compared
     * @return integer 1 ($a > $b), 0 (equal), or -1 ($a < $b)
     * @todo Finish documenting this function
     */
    protected function sort_sortorder_asc(&$a, &$b) {
        return $this->sort_asc($a, $b, 'sortorder');
    }

    /**
     * sort_asc
     *
     * @param object $a (passed by reference) an object to be compared
     * @param object $b (passed by reference) an object to be compared
     * @param string $field the name of the field on which $a and $b are to be compared
     * @return integer 1 ($a > $b), 0 ($a==$b), or -1 ($a < $b)
     * @todo Finish documenting this function
     */
    protected function sort_asc(&$a, &$b, $field) {
        if ($a->$field < $b->$field) {
            return -1; // $a before $b
        }
        if ($a->$field > $b->$field) {
            return 1; // $a after $b
        }
        // equal values
        return 0;
    }


    /**
     * context
     *
     * a wrapper method to offer consistent API to get contexts
     * in Moodle 2.0 and 2.1, we use get_context_instance() function
     * in Moodle >= 2.2, we use static context_xxx::instance() method
     *
     * @param integer $contextlevel
     * @param integer $instanceid (optional, default=0)
     * @param int $strictness (optional, default=0 i.e. IGNORE_MISSING)
     * @return required context
     * @todo Finish documenting this function
     */
    public function context($contextlevel, $instanceid=0, $strictness=0) {
        if (class_exists('context_helper')) {
            $classname = context_helper::get_class_for_level($contextlevel);
            return $classname::instance($instanceid, $strictness);
        } else {
            return get_context_instance($contextlevel, $instanceid);
        }
    }

    /**
     * textlib
     *
     * a wrapper method to offer consistent API for textlib class
     * in Moodle 2.0 and 2.1, $textlib is first initiated, then called.
     * in Moodle >= 2.2, we use only static methods of the "textlib" class.
     *
     * @param string $method
     * @param mixed any extra params that are required by the textlib $method
     * @return result from the textlib $method
     * @todo Finish documenting this function
     */
    public function textlib() {
        if (method_exists('textlib', 'textlib')) {
            $textlib = textlib_get_instance();
        } else {
            $textlib = 'textlib'; // Moodle >= 2.2
        }
        $args = func_get_args();
        $method = array_shift($args);
        $callback = array($textlib, $method);
        return call_user_func_array($callback, $args);
    }
}

/**
 * taskchain_optional_param_array
 *
 * @param xxx $parname
 * @param xxx $default
 * @param xxx $type
 * @return xxx
 * @todo Finish documenting this function
 */
function taskchain_optional_param_array($parname, $default, $type) {
    if (func_num_args() != 3 or empty($parname) or empty($type)) {
        throw new coding_exception('optional_param_array() requires $parname, $default and $type to be specified (parameter: '.$parname.')');
    }

    if (isset($_POST[$parname])) {       // POST has precedence
        $param = $_POST[$parname];
    } else if (isset($_GET[$parname])) {
        $param = $_GET[$parname];
    } else {
        return $default;
    }
    if (!is_array($param)) {
        debugging('optional_param_array() expects array parameters only: '.$parname);
        return $default;
    }

    $result = array();
    foreach($param as $key=>$value) {
        if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
            debugging('Invalid key name in optional_param_array() detected: '.$key.', parameter: '.$parname);
            continue;
        }
        if (is_array($value) && function_exists('clean_param_array')) {
            $result[$key] = clean_param_array($value, $type, true);
        } else {
            $result[$key] = clean_param($value, $type);
        }
    }

    return $result;
}
