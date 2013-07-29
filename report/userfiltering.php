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
 * mod/taskchain/report/userfiltering.php
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
require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * taskchain_user_filtering
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class taskchain_user_filtering extends user_filtering {

    /**
     * get_field
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    function get_field($fieldname, $advanced)  {
        // taskchain version of standard function

        $default = get_user_preferences('taskchain_'.$fieldname, '');
        $rawdata = data_submitted();
        if ($rawdata && isset($rawdata->$fieldname) && ! is_array($rawdata->$fieldname)) {
            $default = optional_param($fieldname, $default, PARAM_ALPHANUM);
        }
        unset($rawdata);

        switch ($fieldname) {
            case 'group':
            case 'grouping':
                return new taskchain_filter_group($fieldname, $advanced, $default);
            case 'grade':
                $label = get_string('grade');
                return new taskchain_filter_grade($fieldname, $label, $advanced, $default);
            case 'timemodified':
                $label = get_string('time', 'quiz');
                return new user_filter_date($fieldname, $label, $advanced, $fieldname);
            case 'status':
                return new taskchain_filter_status($fieldname, $advanced, $default);
            case 'duration':
                $label = get_string('duration', 'taskchain');
                return new taskchain_filter_duration($fieldname, $label, $advanced, $default);
            case 'penalties':
                $label = get_string('penalties', 'taskchain');
                return new taskchain_filter_number($fieldname, $label, $advanced, $default);
            case 'score':
                $label = get_string('score', 'quiz');
                return new taskchain_filter_number($fieldname, $label, $advanced, $default);
            default:
                // other fields (e.g. from user record)
                return parent::get_field($fieldname, $advanced);
        }
    }

    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array sql string and $params
     */
    function get_sql_filter($extra='', array $params=null) {
        list($filter, $params) = parent::get_sql_filter($extra, $params);

        // remove empty " AND " conditions at start, middle and end of filter
        $search = array('/^(?: AND )+/', '/(<= AND )(?: AND )+/', '/(?: AND )+$/');
        $filter = preg_replace($search, '', $filter);

        return array($filter, $params);
    }

    /**
     * Returns sql where statement based on active user filters
     *
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array sql string and $params
     */
    function get_sql_filter_attempts($extra='', $params=null) {
        global $SESSION;

        $filters = array();
        if ($extra) {
            $filters[] = $extra;
        }
        if (is_null($params)) {
            $params = array();
        } else if (! is_array($params)) {
            $params = (array)$params;
        }

        if (! empty($SESSION->user_filtering)) {
            foreach ($SESSION->user_filtering as $fieldname=>$fielddata) {

                if (! array_key_exists($fieldname, $this->_fields)) {
                    continue;
                }

                $field = $this->_fields[$fieldname];
                if (! method_exists($field, 'get_sql_filter_attempts')) {
                    continue;
                }

                foreach($fielddata as $data) {
                    list($f, $p) = $field->get_sql_filter_attempts($data);
                    if ($f) {
                        $filters[] = $f;
                        $params = array_merge($params, $p);
                    }
                }
            }
        }

        $filter = implode(' AND ', $filters);
        return array($filter, $params);
    }
}

/**
 * taskchain_filter_group
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class taskchain_filter_group extends user_filter_select {
    /**
     * Constructor
     * @uses $TC
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default option
     */
    function __construct($filtername, $advanced, $default=null) {
        global $TC; // taskchain object

        $label = '';
        $options = array();

        $strgroup = get_string('group', 'group');
        $strgrouping = get_string('grouping', 'group');

        if ($groupings = groups_get_all_groupings($TC->course->id)) {
            $label = $strgrouping;
            $has_groupings = true;
        } else {
            $has_groupings = false;
            $groupings = array();
        }

        if ($groups = groups_get_all_groups($TC->course->id)) {
            if ($label) {
                $label .= ' / ';
            }
            $label .= $strgroup;
            $has_groups = true;
        } else {
            $has_groups = false;
            $groups = array();
        }

        foreach ($groupings as $gid => $grouping) {
            if ($has_groups) {
                $prefix = $strgrouping.': ';
            } else {
                $prefix = '';
            }
            if ($members = groups_get_grouping_members($gid)) {
                $options["grouping$gid"] = $prefix.format_string($grouping->name).' ('.count($members).')';
            }
        }

        foreach ($groups as $gid => $group) {
            if ($members = groups_get_members($gid)) {
                if ($has_groupings) {
                    $prefix = $strgroup.': ';
                } else {
                    $prefix = '';
                }
                $options["group$gid"] = $prefix.format_string($group->name).' ('.count($members).')';
            }
        }

        parent::user_filter_select($filtername, $label, $advanced, '', $options, $default);
    }

    /**
     * setupForm
     *
     * @param xxx $mform (passed by reference)
     */
    function setupForm(&$mform)  {
        // only setup the select element if it has any options
        if (count($this->_options)) {
            parent::setupForm($mform);
        }
    }

    /**
     * get_sql_filter
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter($data)  {
        global $DB, $TC;

        $filter = '';
        $params = array();

        if (($value = $data['value']) && ($operator = $data['operator'])) {

            $userids = array();
            if (substr($value, 0, 5)=='group') {
                if (substr($value, 5, 3)=='ing') {
                    $gids = groups_get_all_groupings($TC->course->id);
                    $gid = intval(substr($value, 8));
                    if ($gids && array_key_exists($gid, $gids) && ($members = groups_get_grouping_members($gid))) {
                        $userids = array_keys($members);
                    }
                } else {
                    $gids = groups_get_all_groups($TC->course->id);
                    $gid = intval(substr($value, 5));
                    if ($gids && array_key_exists($gid, $gids) && ($members = groups_get_members($gid))) {
                        $userids = array_keys($members);
                    }
                }
            }

            if (count($userids)) {
                switch($operator) {
                    case 1: // is equal to
                        list($filter, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, '', true);
                        break;
                    case 2: // isn't equal to
                        list($filter, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, '', false);
                        break;
                }
                if ($filter) {
                    $filter = 'id '.$filter;
                }
            }
        }

        // no userids found
        return array($filter, $params);
    }
}

/**
 * taskchain_filter_status
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class taskchain_filter_status extends user_filter_select {
    /**
     * Constructor
     *
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default option
     */
    function __construct($name, $advanced, $default=null) {
        $label = get_string($name, 'taskchain');
        $options = taskchain_available::statuses_list();
        parent::__construct($name, $label, $advanced, '', $options, $default);
    }

    /**
     * get_sql_filter
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter($data)  {
        // this field type doesn't affect the selection of users
        return array('', array());
    }

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_status'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            switch($operator) {
                case 1: // is equal to
                    $filter = 'status=:'.$name;
                    $params[$name] = $value;
                    break;
                case 2: // isn't equal to
                    $filter = 'status<>:'.$name;
                    $params[$name] = $value;
                    break;
            }
        }
        return array($filter, $params);
    }
}

/**
 * taskchain_filter_number
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class taskchain_filter_number extends user_filter_select {
    /**
     * Constructor
     *
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default option
     */
    function __construct($name, $label, $advanced, $default=null) {
        parent::user_filter_type($name, $label, $advanced);

        $this->_field   = '';
        $this->_default = $default;
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function get_operators() {
        return array(0 => get_string('isanyvalue','filters'),
                     1 => get_string('islessthan', 'taskchain'),
                     2 => get_string('isequalto','filters'),
                     3 => get_string('isgreaterthan', 'taskchain'));
    }

    /**
     * setupForm
     *
     * @param xxx $mform (passed by reference)
     */
    function setupForm(&$mform)  {
        $objs = array(
            $mform->createElement('select', $this->_name.'_op', null, $this->get_operators()),
            $mform->createElement('text', $this->_name, null, array('size' => '3'))
        );
        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->disabledIf($this->_name, $this->_name.'_op', 'eq', 0);

        $mform->setType($this->_name.'_op', PARAM_INT);
        $mform->setType($this->_name, PARAM_INT);

        if (!is_null($this->_default)) {
            $mform->setDefault($this->_name, $this->_default);
        }

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->_name;
        $operator = $field.'_op';

        if (array_key_exists($field, $formdata) and !empty($formdata->$operator)) {
            return array('operator' => (int)$formdata->$operator,
                         'value'    => (int)$formdata->$field);
        }

        return false;
    }

    /**
     * get_sql_filter
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter($data)  {
        // this field type doesn't affect the selection of users
        return array('', array());
    }

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_number'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            $field = $this->_name;
            switch($operator) {
                case 1: // less than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
                case 2: // equal to
                    $filter = $field.'=:'.$name;
                    $params[$name] = $value;
                    break;
                case 3: // greater than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
            }
        }
        return array($filter, $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->get_operators();

        if (empty($operator)) {
            return '';
        }

        $a = (object)array(
            'label'    => $this->_label,
            'value'    => '"'.s($value).'"',
            'operator' => $operators[$operator]
        );

        return get_string('selectlabel', 'filters', $a);
    }
}

/**
 * taskchain_filter_grade
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class taskchain_filter_grade extends taskchain_filter_number {

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_grade'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            $field = 'gg.rawgrade';
            switch($operator) {
                case 1: // less than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
                case 2: // equal to
                    $filter = $field.'=:'.$name;
                    $params[$name] = $value;
                    break;
                case 3: // greater than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
            }
        }
        return array($filter, $params);
    }
}

/**
 * taskchain_filter_duration
 *
 * @copyright 2010 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class taskchain_filter_duration extends taskchain_filter_number {

    /**
     * setupForm
     *
     * @param xxx $mform (passed by reference)
     */
    function setupForm(&$mform)  {
        $objs = array(
            $mform->createElement('select', $this->_name.'_op', null, $this->get_operators()),
            $mform->createElement('duration', $this->_name, null, array('optional'=>0, 'defaultunit'=>1))
        );
        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->disabledIf($this->_name.'_grp', $this->_name.'_op', 'eq', 0);

        $mform->setType($this->_name.'_op', PARAM_INT);
        $mform->setType($this->_name.'[number]', PARAM_INT);
        $mform->setType($this->_name.'[timeunit]', PARAM_INT);

        if (!is_null($this->_default)) {
            $mform->setDefault($this->_name, $this->_default);
        }

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->get_operators();

        if (empty($operator)) {
            return '';
        }

        $a = (object)array(
            'label'    => $this->_label,
            'value'    => '"'.s(format_time($value)).'"',
            'operator' => $operators[$operator]
        );

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_duration'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            $field = '(timemodified - timestart)'; // $this->_name;
            switch($operator) {
                case 1: // less than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
                case 2: // equal to
                    $filter = $field.'=:'.$name;
                    $params[$name] = $value;
                    break;
                case 3: // greater than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
            }
        }
        return array($filter, $params);
    }
}
