<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$forminfo =array(
                array('name'=>'dev_buttons', 'label'=>'Buttons Configuration'),
                array('name'=>'button', 'label'=>'Buttons', 'help'=>'help')
                );
//$buttons_type=  array("empty","line","service","feature","speeddial");
//   "feature","service" -- Add leter !
$buttons_type=  array("empty","line","silent","monitor","speeddial","feature","adv.line","service");
$feature_list=  array('parkinglot'=>'Park Slots','monitor'=> "Record Calls",'devstate'=> "Change Status");

if ($_REQUEST['tech_hardware'] === 'cisco') {
    $lines_list = $this->dbinterface->getSccpDeviceTableData('SccpExtension');
} else {
    $lines_list = $this->dbinterface->getSipTableData('extensionList');
}

$hint_list  = $this->getHintInformation(true, array('context'=>'park-hints')) ;

$line_id =0;
$max_buttons =56;     //Don't know hardware type so set a maximum. On save, this is set to actual max buttons
$show_buttons =1;
$db_buttons_by_instance = array();
$uses_zero_instance = false;

/**
 * Map GUI button slot (0-based) to sccpbuttonconfig row.
 * DB may use instance 0-based (chan_sccp) or 1-based (manager save).
 */
$sccp_get_button_row = function ($slot) use (&$db_buttons_by_instance, &$uses_zero_instance) {
    $instance = $uses_zero_instance ? $slot : ($slot + 1);
    return $db_buttons_by_instance[$instance] ?? null;
};

/**
 * Return extension id from a line button name (strips @instance and !silent).
 */
$sccp_line_exten_from_name = function ($name) {
    $name = (string)$name;
    if ($name === '') {
        return '';
    }
    $name = preg_replace('/!.*$/', '', $name);
    $at = strpos($name, '@');
    if ($at !== false) {
        $name = substr($name, 0, $at);
    }
    return $name;
};

if (!empty($_REQUEST['id'])) {
    $dev_id = $_REQUEST['id'];
    $db_buttons = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_buttons', array("id" => $dev_id));
    $db_device = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array("id" => $dev_id));
    $show_buttons = $db_device['buttons'];
    if (!empty($db_device['addon_buttons'])) {
        $show_buttons += $db_device['addon_buttons'];
    }
    //$show_buttons = $max_buttons;
}
if (!empty($_REQUEST['new_id'])) {
    $val = $_REQUEST['type'];
    $dev_schema =  $this-> getSccpModelInformation('byid', false, "all", array('model' =>$val));
//   $db_device = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_byid', array("id" => $val));
    $show_buttons = $dev_schema[0]['buttons'];
    if (!empty($_REQUEST['addon'])) {
        $val = $_REQUEST['addon'];
        $dev_schema =  $this-> getSccpModelInformation('byid', false, "all", array('model' =>$val));
        $show_buttons += $dev_schema[0]['buttons'];
    }
    //$show_buttons = $max_buttons;
}
if (!empty($_REQUEST['ru_id'])) {
    $dev_id = $_REQUEST['ru_id'];
    $db_buttons = $this->dbinterface->getSccpDeviceTableData('get_sccpdevice_buttons', array("id" => $dev_id));
    $show_buttons = $max_buttons;
}

if (!empty($db_buttons) && is_array($db_buttons)) {
    foreach ($db_buttons as $row) {
        $inst = (int)$row['instance'];
        $db_buttons_by_instance[$inst] = $row;
        if ($inst === 0) {
            $uses_zero_instance = true;
        }
    }
    unset($db_buttons);
}

$line_names = array();
foreach ($lines_list as $line) {
    $line_names[(string)$line['name']] = true;
}

/**
 * Validate parsed button config; invalid DB rows must not appear as wrong GUI values.
 */
$sccp_button_config_valid = function ($buttontype, $name, $options, $feature_list, $line_names) use ($sccp_line_exten_from_name) {
    switch ($buttontype) {
        case 'empty':
            return true;
        case 'line':
        case 'silent':
            $ext = $sccp_line_exten_from_name($name);
            return $ext !== '' && isset($line_names[$ext]);
        case 'adv.line':
            $ext = $sccp_line_exten_from_name($name);
            return $ext !== '' && isset($line_names[$ext]);
        case 'monitor':
            $ext = $sccp_line_exten_from_name($name);
            if ($ext !== '' && isset($line_names[$ext])) {
                return true;
            }
            foreach ($options as $opt) {
                if (strpos((string)$opt, '@') !== false) {
                    $hint_ext = $sccp_line_exten_from_name($opt);
                    if ($hint_ext !== '' && isset($line_names[$hint_ext])) {
                        return true;
                    }
                }
            }
            return false;
        case 'speeddial':
            return trim((string)$name) !== '' || trim((string)($options[0] ?? '')) !== '';
        case 'feature':
            $ftype = (string)($options[0] ?? '');
            return $ftype !== '' && array_key_exists($ftype, $feature_list);
        case 'service':
            $opts = is_array($options) ? implode(',', $options) : (string)$options;
            return trim((string)$name) !== '' || trim($opts) !== '';
        default:
            return false;
    }
};

?>

<form autocomplete="off" name="frm_editbuttons" id="frm_editbuttons" class="fpbx-submit" action="" method="post" data-id="hw_edit">
    <input type="hidden" name="category" value="frm_editbuttons">
    <input type="hidden" name="Submit" value="Submit">
    <input type="hidden" name="buttonscount" id="buttonscount" value="<?php echo $show_buttons;?>">
    <input type="hidden" name="devButtonCnt" id="devButtonCnt" value="<?php echo (!empty($db_device['buttons']))?$db_device['buttons']:0;?>">
    <input type="hidden" name="addonCnt" id="addonCnt" value="<?php echo (!empty($db_device['dns']))?$db_device['dns']:0;?>">
    <div class="section-title" data-for="<?php echo $forminfo[0]['name'];?>">
        <h3><i class="fa fa-minus"></i><?php echo _($forminfo[0]['label']) ?></h3>
    </div>
    <div class="section" data-id="<?php echo $forminfo[0]['name'];?>">
    <div class="row"> <div class="form-group">
            <div class="col-sm-2">
                <label class="control-label">Help</label>
                <i class="fa fa-question-circle fpbx-help-icon" data-for="frmbuttons"></i>
            </div>
            <div class="col-sm-10">
                <span id="frmbuttons-help" class="help-block fpbx-help-block"><?php echo _("buttons come in the following flavours: <br>
                    <ul>
                    <li>empty: Empty button (no options)</li>
                    <li>line: Registers the line with identifier specified as [name]</li>
                    <li>silent:   buttons equal 'Line' with out ring</li>
                    <li>monitor:  buttons mode speeddial + show status</li>
                    <li>speeddial: Adds a speeddial with label [name] and [option1] as number Optionally, [option2] can be used to specify a hint by extension@context as usual.</li>
                    <li>service (not implemented): Adds a service url Feature buttons have an on/off status represented on the device with a tick-box and can be used to set the device in a particular state. Currently Possible [option1],[option2] combinations:</li>
                    <ul>
                    <li>privacy,callpresent = Make a private call, number is suppressed</li><li>privacy,hint = Make a private call, hint is suppressed</li><li>cfwdall,number = Forward all calls </li><li>cfwbusy,number = Forward on busy</li><li>
                    cfwnoaswer,number = Forward on no-answer (not implemented yet)<br> DND,busy = Do-not-disturb, return Busy signal to Caller <br> DND,silent = Do-not-disturb, return nothing to caller <br>
                    monitor = Record Calls using AutoMon (asterisk 1.6.x only)</li><li>devstate,custom_devstate = Device State Feature Button (asterisk 1.6.1 and up). custom_devstate is the name of the custom devicestate to be toggled (How to use devicestate)
                    hold = To be implemented</li><li>transfer = To be implemented</li><li>multiblink = To be implemented</li><li>mobility = To be implemented</li><li>conference = To be implemented</li>
                    </ui></ui>");?></span>
            </div>

    </div></div>
    <?php
    for ($line_id = 0; $line_id <$max_buttons; $line_id ++) {
        $show_form_mode = '';
        $btn_row = $sccp_get_button_row($line_id);
        $defaul_tv = (empty($btn_row)) ?  "empty": $btn_row['buttontype'];
        $defaul_btn = (empty($btn_row)) ?  "": $btn_row['name'];
        $defaul_opt = (empty($btn_row)) ?  array(''): explode(',', (string)($btn_row['options'] ?? ''));

        $show_form_mode = $defaul_tv;
        $def_hint = '';       // Hint check Box
        $def_hint_btn = '';   // Hint Combo Box
        $def_park = '';       // Hint check Box
        $def_silent = '';
        $defaul_advline = '';
        $defaul_ftr = '';
        $defaul_fcod = '';
        $defaul_svc_url = '';
        if (strpos((string)$defaul_btn, '@') > 0) {
            $defaul_tv = 'adv.line';
            $show_form_mode = 'adv.line';
            $parts = explode('@', (string)$defaul_btn, 2);
            $defaul_btn = $parts[0];
            $defaul_advline = $parts[1] ?? '';
        }
        // New device: default button 0 to line — not when DB row exists but is invalid
        if ($line_id == 0 && $defaul_tv == 'empty' && empty($btn_row)) {
            $defaul_tv = 'line';
            $show_form_mode = 'line';
        }
        if (stripos((string)$defaul_btn, '!') !== false) {
            $defaul_btn = preg_replace('/!.*$/', '', (string)$defaul_btn);
            $defaul_tv = 'silent';
            $def_silent = 'checked';
        }
        if ($defaul_tv == "service") {
            $defaul_svc_url = $btn_row['options'] ?? '';
        }
        if ($defaul_tv == "feature") {
            $defaul_ftr = $defaul_opt[0];
            $defaul_fcod = (empty($defaul_opt[1])) ?  '': $defaul_opt[1];
            $def_park = (empty($defaul_opt[2])) ?  '': 'checked';
//                print_r($defaul_opt);
        }

        foreach ($defaul_opt as $data_i) {
            if (strpos($data_i, '@')>0) {
                $hint_parts = explode('@', $data_i, 2);
                $test_btn = $hint_parts[0];
                $def_hint = 'checked';
                $def_hint_btn = $data_i;
                if ($test_btn == $defaul_opt[0]) {
                    foreach ($lines_list as $data) {
                        if ($data['name']==$test_btn) {
                                $show_form_mode = 'line';
                                $defaul_tv = 'monitor';
                                $defaul_btn = $test_btn;
                                break;
                        }
                    }
                }
            }
        }

        if (!empty($btn_row) && !$sccp_button_config_valid($defaul_tv, $defaul_btn, $defaul_opt, $feature_list, $line_names)) {
            if ($line_id == 0) {
                $defaul_btn = '';
                $defaul_advline = '';
                $defaul_opt = array('');
                $def_hint = '';
                $def_hint_btn = '';
                $def_park = '';
                $def_silent = '';
                $defaul_ftr = '';
                $defaul_fcod = '';
                $defaul_svc_url = '';
                if ($defaul_tv !== 'adv.line') {
                    $defaul_tv = 'line';
                    $show_form_mode = 'line';
                }
            } else {
                $defaul_tv = 'empty';
                $show_form_mode = 'empty';
                $defaul_btn = '';
                $defaul_advline = '';
                $defaul_opt = array('');
                $def_hint = '';
                $def_hint_btn = '';
                $def_park = '';
                $def_silent = '';
                $defaul_ftr = '';
                $defaul_fcod = '';
                $defaul_svc_url = '';
            }
        }

        echo '<!-- Begin button :'.$line_id.' -->';
        echo '<div class="line_button element-container" '.(($line_id < $show_buttons)?"":"hidden ").'data-id="'.$line_id.'">';

        ?>
            <div class="row"> <div class="form-group">
                    <div class="col-sm-2">
                        <label class="control-label" for="<?php echo $forminfo[1]['name'].$line_id; ?> "><?php echo _($forminfo[1]['label'].$line_id).(($line_id =="0")?' Default ':''); ?></label>
                    </div>
                    <div class="col-sm-5">
                        <div class="col-xs-3">
<!--  Line Type Select                        -->
                        <select class="form-control buttontype" data-id="<?php echo $line_id;?>" name="<?php echo $forminfo[1]['name'].$line_id.'_type';?>">
                    <?php
                    if ($line_id == 0) {
                        echo '<option value="line" '.(($defaul_tv == 'line')?'selected':'').' >DEF LINE</option>';
                        echo '<option value="adv.line" '.(($defaul_tv == 'adv.line')?'selected':'').' >DEF ADV.LINE</option>';
                    } else {
                        foreach ($buttons_type as $data) {
                            $select = (($data == $defaul_tv)?"selected":"");
                            echo '<option value="'.$data.'" '.$select.' >'.$data.'</option>';
                        }
                    }
                    ?>
                        </select>
                        </div>
<!--  if Line Type = feature Show Futures -->
                        <div class="col-xs-7">
                        <select data-type="feature" class ="futuretype form-control lineid_<?php echo $line_id.(($show_form_mode=='feature')?'':' hidden');?>" data-id="<?php echo $line_id;?>"  name="<?php echo $forminfo[1]['name'].$line_id.'_feature';?>" >
                        <?php
                        foreach ($feature_list as $fkey => $fval) {
                            $select = (($fkey == $defaul_ftr)?"selected":"");
                            echo '<option value="'.$fkey.'" '.$select.' >'.$fval.'</option>';
                        }
                        ?>
                        </select>
<!--  if Line Type = line Show SCCP Num -->
                        <select data-type='line' class ="form-control lineid_<?php echo $line_id.(($show_form_mode=='line' || $show_form_mode=='adv.line')?'':' hidden');?>" name="<?php echo $forminfo[1]['name'].$line_id.'_line';?>" id="<?php echo $forminfo[1]['name'].$line_id.'_line';?>">
                        <?php
                        echo '<option value=""'.($defaul_btn === '' ? ' selected="selected"' : '').'>--</option>';
                        foreach ($lines_list as $data) {
                            $select = (($defaul_btn !== '') && ($data['name']==$defaul_btn))?'selected="selected"':"";
                            echo '<option value="'.$data['name'].'" '.$select.' >'.$data['name'].' / '.$data['label'].'</option>';
                        }
                        ?>
                        </select>
<!--  if Line Type = Othe Show  Input -->
                        <div data-type='speeddial' class="lineid_<?php echo $line_id.(($show_form_mode=='speeddial')? '':' hidden');?>" >
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_input"  name="'.$forminfo[1]['name'].$line_id.'_input" placeholder="Name" value="'.htmlspecialchars((string)$defaul_btn).'" >';
                            ?>
                        </div>
<!--  if Line Type = service Show Label -->
                        <div data-type='service' class="lineid_<?php echo $line_id.(($show_form_mode=='service')? '':' hidden');?>" >
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_slabel" name="'.$forminfo[1]['name'].$line_id.'_slabel" placeholder="Label" value="'.htmlspecialchars((string)$defaul_btn).'">';
                            ?>
                        </div>
                        </div>

                    </div>
                    <div class="col-md-5">
<!--  if Line Type = speeddial Show  Hint line -->
                        <div data-type='hintline' class="lineid_<?php echo $line_id.(($show_form_mode=='speeddial')? '':' hidden');?>" name="<?php echo $forminfo[1]['name'].$line_id.'_hint';?>">
                            <?php
                            echo '<div class="col-xs-5">';
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_phone"  name="'.$forminfo[1]['name'].$line_id.'_phone" placeholder="Phone" value="'.$defaul_opt[0].'">';
                            echo '</div><div class="col-xs-2 radioset" data-toggle="buttons">';
                            echo '<input class="form-control" type="checkbox" name="'.$forminfo[1]['name'].$line_id.'_hint" id="'.$forminfo[1]['name'].$line_id.'_hint" '.$def_hint.' value= "hint">';
                            echo '<label for="'.$forminfo[1]['name'].$line_id.'_hint">hints</label>';
                            echo '</div><div class="col-xs-5">';

                            echo '<select  class="form-control" name="'.$forminfo[1]['name'].$line_id.'_hline" >';
                            echo '<option value="" '.(($def_hint_btn=="")?"selected":"").' >-- no hint --</option>';

                            foreach ($hint_list as $data) {
                                $select = (($data['key']==$def_hint_btn)?"selected":"");
                                echo '<option value="'.$data['key'].'" '.$select.' >'.$data['exten'].' / '.$data['label'].'</option>';
                            }
                            echo '</select>';
                            echo '</div>';
                            ?>
                        </div>
<!--  if Line Type = feature Show Futures  Park -->
                        <div data-type='feature' class="lineid_<?php echo $line_id.(($show_form_mode=='feature')? '':' hidden');?>" name="<?php echo $forminfo[1]['name'].$line_id.'_hint';?>">
                            <div class="col-xs-4">
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_flabel"  name="'.$forminfo[1]['name'].$line_id.'_flabel" placeholder="Display Label" value="'.$defaul_btn.'" >';
                            ?>
                            </div>
                            <div class="col-xs-4">
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_fvalue"  name="'.$forminfo[1]['name'].$line_id.'_fvalue" placeholder="code" value="'.$defaul_fcod.'" >';
                            ?>
                            </div>
                        </div>
<!--  if Line Type = Advanced Show  Hint line -->

                        <div data-type='adv_line' class="lineid_<?php echo $line_id.(($show_form_mode=='adv.line')? '':' hidden');?>" name="<?php echo $forminfo[1]['name'].$line_id.'_hint';?>">
                            <div class="col-xs-5">
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_advline"  name="'.$forminfo[1]['name'].$line_id.'_advline" placeholder="[+=][01]:[cidname]" value="'.$defaul_advline.'" >';
                            ?>
                            </div>
                            <div class="col-xs-5">
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_advopt"  name="'.$forminfo[1]['name'].$line_id.'_advopt" placeholder="ButtonLabel,Options" value="'.implode(',', $defaul_opt).'" >';
                            ?>
                            </div>
                        </div>
<!--  if Line Type = feature Show Futures  Park -->
                        <div data-type='featurep' class="lineid_<?php echo $line_id.(($show_form_mode=='feature')? (($defaul_ftr=='parkinglot')? ' ':' hidden'):' hidden');?>" name="<?php echo $forminfo[1]['name'].$line_id.'_park';?>">
                            <div class="col-xs-4">
                                <div class="radioset" data-toggle="buttons">
                            <?php
                                echo '<input class="form-control" type="checkbox" name="'.$forminfo[1]['name'].$line_id.'_retrieve" id="'.$forminfo[1]['name'].$line_id.'_retrieve" '.$def_park.' value="retrieve">';
                                echo '<label for="'.$forminfo[1]['name'].$line_id.'_retrieve">RetrieveSingle</label>';
                            ?>
                                </div>
                             </div>
                        </div>

<!--  if Line Type = service Show URL -->
                        <div data-type='service' class="lineid_<?php echo $line_id.(($show_form_mode=='service')? '':' hidden');?>">
                            <div class="col-xs-12">
                            <?php
                            echo '<input class="form-control" type="text" id="'.$forminfo[1]['name'].$line_id.'_surl" name="'.$forminfo[1]['name'].$line_id.'_surl" placeholder="Service URL" value="'.htmlspecialchars($defaul_svc_url).'">';
                            ?>
                            </div>
                        </div>

                    </div>

                </div></div>
        </div>
        <?php
        echo '<!-- End button :'.$line_id.' -->';
    }

    ?>



    </div>
</form>
<div class="section-butom" data-for="<?php echo $forminfo[0]['name'];?>">
        <h3></h3>
</div>
