<?php

if ($system->checkForRight('SQLCONSOLE')) {

    $alterconf = $ubillingConfig->getAlter();
    $onePunch = new OnePunch();

    //new script creation
    if (wf_CheckPost(array('newscriptalias', 'newscriptname', 'newscriptcontent'))) {
        $punchCreateResult = $onePunch->createScript($_POST['newscriptalias'], $_POST['newscriptname'], $_POST['newscriptcontent']);
        if (!empty($punchCreateResult)) {
            show_error($punchCreateResult);
        } else {
            rcms_redirect($onePunch::URL_DEVCON);
        }
    }

    //existing script deletion
    if (wf_CheckGet(array('delscript'))) {
        $punchDeleteResult = $onePunch->deleteScript($_GET['delscript']);
        if (!empty($punchDeleteResult)) {
            show_error($punchDeleteResult);
        } else {
            rcms_redirect($onePunch::URL_DEVCON);
        }
    }

    //editing existing script
    if (wf_CheckPost(array('editscriptid', 'editscriptoldalias', 'editscriptname', 'editscriptalias', 'editscriptcontent'))) {
        $onePunch->saveScript();
        rcms_redirect($onePunch::URL_DEVCON . '&editscript=' . $_POST['editscriptalias']);
    }

//construct query forms
    $sqlinputs = wf_Link("?module=sqlconsole", 'SQL Console', false, 'ubButton');
    $sqlinputs.=wf_Link("?module=sqlconsole&devconsole=true", 'PHP Console', false, 'ubButton');
    if (cfr('ROOT')) {
        $sqlinputs.=wf_Link("?module=migration", __('Migration'), false, 'ubButton');
        $sqlinputs.=wf_Link("?module=migration2", __('Migration') . ' 2', false, 'ubButton');
        if (cfr('MIKMIGR')) {
            $sqlinputs.=wf_Link("?module=mikbill_migration", __('Migration') . ' mikbill', true, 'ubButton');
        }
    }
    if (wf_CheckPost(array('sqlq'))) {
        if ($alterconf['DEVCON_SQL_KEEP']) {
            $startQuery = trim($_POST['sqlq']);
        } else {
            $startQuery = '';
        }
    } else {
        $startQuery = '';
    }
    $sqlinputs.=wf_TextArea('sqlq', '', $startQuery, true, '80x10');
    $sqlinputs.=wf_CheckInput('tableresult', 'Display query result as table', true, false);
    $sqlinputs.=wf_Submit('Process query');
    $sqlform = wf_Form('', 'POST', $sqlinputs, 'glamour');

    $phpinputs = wf_Link("?module=sqlconsole", 'SQL Console', false, 'ubButton');
    $phpinputs.=wf_Link("?module=sqlconsole&devconsole=true", 'PHP Console', false, 'ubButton');
    if (cfr('ROOT')) {
        $phpinputs.=wf_Link("?module=migration", 'Migration', false, 'ubButton');
        $phpinputs.=wf_Link("?module=migration2", __('Migration') . ' 2', false, 'ubButton');
    }
    if (cfr('MIKMIGR')) {
        $sqlinputs.=wf_Link("?module=mikbill_migration", __('Migration') . ' mikbill', true, 'ubButton');
    }
//is template run or clear area?
    if (wf_CheckGet(array('runscript'))) {
        $runcode = $onePunch->getScriptContent($_GET['runscript']);
    } else {
        $runcode = '';
        if ($alterconf['DEVCON_SQL_KEEP']) {
            if (wf_CheckPost(array('phpq'))) {
                $runcode = $_POST['phpq'];
            }
        } else {
            $runcode = '';
        }
    }
    $phpinputs.=wf_TextArea('phpq', '', $runcode, true, '80x10');
    $phpinputs.=wf_CheckInput('phphightlight', 'Hightlight this PHP code', true, true);
    $phpinputs.=wf_Submit('Run this code inside framework');
    $phpform = wf_Form('?module=sqlconsole&devconsole=true', 'POST', $phpinputs, 'glamour');

//php console grid assemble
    $phpcells = wf_TableCell($phpform, '50%', '', 'valign="top"');
    if (wf_CheckGet(array('scriptadd'))) {
        //show script creation form
        $phpcells.= wf_TableCell($onePunch->renderCreateForm(), '50%', '', 'valign="top"');
    } else {
        if (wf_CheckGet(array('editscript'))) {
            //show scripts edit form
            $phpcells.=wf_TableCell($onePunch->renderEditForm($_GET['editscript']), '50%', '', 'valign="top"');
        } else {
            //show scripts list
            $punchScriptsList=$onePunch->renderScriptsList();
            $punchScriptsList.=wf_tag('br');
            $punchScriptsList.= wf_Link($onePunch::URL_DEVCON . '&scriptadd=true', web_icon_create() . ' ' . __('Create'), true, 'ubButton');
            $phpcells.= wf_TableCell($punchScriptsList, '50%', '', 'valign="top"');
        }
    }

    $phprows = wf_TableRow($phpcells);
    $phpgrid = wf_TableBody($phprows, '100%', '0', '');

//show needed form
    if (!isset($_GET['devconsole'])) {
        show_window(__('SQL Console'), $sqlform);
    } else {
        show_window(__('Developer Console'), $phpgrid);
    }

// SQL console processing
    if (isset($_POST['sqlq'])) {
        $newquery = trim($_POST['sqlq']);

        if (!empty($newquery)) {
            $stripquery = substr($newquery, 0, 70) . '..';
            log_register('SQLCONSOLE ' . $stripquery);
            ob_start();

            // commented due Den1xxx patch
            if (!extension_loaded('mysql')) {
                $queried = mysqli_query($loginDB, $newquery);
            } else {
                $queried = mysql_query($newquery);
            }
            if ($queried === false) {
                ob_end_clean();
                return show_window('SQL ' . __('Result'), wf_tag('b') . __('Wrong query') . ':' . wf_tag('b', true) . wf_delimiter() . $newquery);
            } else {
                if (!extension_loaded('mysql')) {
                    while (@$row = mysqli_fetch_assoc($queried)) {
                        $query_result[] = $row;
                    }
                } else {
                    while (@$row = mysql_fetch_assoc($queried)) {
                        $query_result[] = $row;
                    }
                }

                $sqlDebugData = ob_get_contents();
                ob_end_clean();
                log_register('SQLCONSOLE QUERYDONE');
                if ($alterconf['DEVCON_VERBOSE_DEBUG']) {
                    show_window(__('Console debug data'), $sqlDebugData);
                }
            } //end of wrong query exeption patch
            if (!empty($query_result)) {
                if (!isset($_POST['tableresult'])) {
                    //raw array result
                    $vdump = var_export($query_result, true);
                } else {
                    //show query result as table
                    $tablerows = '';
                    foreach ($query_result as $eachresult) {
                        $tablecells = wf_TableCell('');
                        $tablecells.=wf_TableCell('');
                        $tablerows.=wf_TableRow($tablecells, 'row1');
                        foreach ($eachresult as $io => $key) {
                            $tablecells = wf_TableCell($io);
                            $tablecells.=wf_TableCell($key);
                            $tablerows.=wf_TableRow($tablecells, 'row3');
                        }
                    }
                    $vdump = wf_TableBody($tablerows, '100%', '0', '');
                }
            } else {
                $vdump = __('Query returned empty result');
            }
        } else {
            $vdump = __('Empty query');
        }

        show_window(__('Result'), '<pre>' . $vdump . '</pre>');
    }


//PHP console processing
    if (isset($_POST['phpq'])) {
        $phpcode = trim($_POST['phpq']);
        if (!empty($phpcode)) {
            //show our code for debug
            if (isset($_POST['phphightlight'])) {
                show_window(__('Running this'), highlight_string('<?php' . "\n" . $phpcode . "\n" . '?>', true));
            }
            //executing it
            $stripcode = substr($phpcode, 0, 70) . '..';
            log_register('DEVCONSOLE ' . $stripcode);
            ob_start();
            eval($phpcode);
            $debugData = ob_get_contents();
            ob_end_clean();
            if ($alterconf['DEVCON_VERBOSE_DEBUG']) {
                show_window(__('Console debug data'), wf_tag('pre') . $debugData) . wf_tag('pre', true);
            }
            log_register('DEVCONSOLE DONE');
        } else {
            show_window(__('Result'), __('Empty code part received'));
        }
    }
} else {
    show_error(__('Access denied'));
}
?>