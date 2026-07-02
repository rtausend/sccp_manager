<?php
/*
 * SCCP Line Edit Form
 * Allows editing of SCCP Line configuration
 */

// Get the line ID from the request
$line_id = !empty($_REQUEST['extdisplay']) ? $_REQUEST['extdisplay'] : '';

error_log("DEBUG form.editline: Started loading, extdisplay={$line_id}");

if (empty($line_id)) {
    echo '<div class="alert alert-danger">' . _("No line ID provided") . '</div>';
    error_log("ERROR form.editline: No line ID provided in request");
    return;
}

// Load line data from database
error_log("DEBUG form.editline: About to query database for line_id={$line_id}");
$lineData = $this->dbinterface->getSccpDeviceTableData('SccpExtension', array('name' => $line_id));

error_log("DEBUG form.editline: Query result: " . json_encode($lineData));

if (empty($lineData) || empty($lineData[0])) {
    echo '<div class="alert alert-danger">' . sprintf(_("Line not found: %s"), htmlspecialchars($line_id)) . '</div>';
    error_log("ERROR form.editline: Line not found for ID: {$line_id}");
    return;
}

$line = $lineData[0];

error_log("DEBUG form.editline: Successfully loaded line, keys: " . json_encode(array_keys($line)));

?>
<div class="fpbx-container container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="display no-border">
                <h1><?php echo sprintf(_("Edit SCCP Extension: %s"), htmlspecialchars($line_id)); ?></h1>
                
                <form id="edit-sccp-line-form" class="fpbx-submit" method="post" autocomplete="off" data-id="sccp_line_edit">
                    <input type="hidden" name="category" value="edit_sccp_line">
                    <input type="hidden" name="action" value="save_line">
                    <input type="hidden" name="line_id" value="<?php echo htmlspecialchars($line_id); ?>">
                    
                    <fieldset>
                        <legend><?php echo _("Line Settings"); ?></legend>
                        
                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_name"><?php echo _("Extension Number"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_name" class="form-control" name="sccp_name" value="<?php echo htmlspecialchars($line['name'] ?? ''); ?>" disabled readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_label"><?php echo _("Display Name"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_label" class="form-control" name="sccp_label" value="<?php echo htmlspecialchars($line['label'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_description"><?php echo _("Description"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_description" class="form-control" name="sccp_description" value="<?php echo htmlspecialchars($line['description'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_cid_name"><?php echo _("Caller ID Name"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_cid_name" class="form-control" name="sccp_cid_name" value="<?php echo htmlspecialchars($line['cid_name'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_cid_num"><?php echo _("Caller ID Number"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_cid_num" class="form-control" name="sccp_cid_num" value="<?php echo htmlspecialchars($line['cid_num'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_context"><?php echo _("Context"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_context" class="form-control" name="sccp_context" value="<?php echo htmlspecialchars($line['context'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_mailbox"><?php echo _("Mailbox"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_mailbox" class="form-control" name="sccp_mailbox" value="<?php echo htmlspecialchars($line['mailbox'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_vmnum"><?php echo _("VM Notify Number"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_vmnum" class="form-control" name="sccp_vmnum" value="<?php echo htmlspecialchars($line['vmnum'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_accountcode"><?php echo _("Account Code"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_accountcode" class="form-control" name="sccp_accountcode" value="<?php echo htmlspecialchars($line['accountcode'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_transfer"><?php echo _("Transfer"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <select id="sccp_transfer" class="form-control" name="sccp_transfer">
                                            <option value="yes" <?php echo ($line['transfer'] === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                            <option value="no" <?php echo ($line['transfer'] === 'no') ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="element-container">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <div class="col-md-3">
                                        <label class="control-label" for="sccp_musicclass"><?php echo _("Music Class"); ?></label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" id="sccp_musicclass" class="form-control" name="sccp_musicclass" value="<?php echo htmlspecialchars($line['musicclass'] ?? 'default'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </fieldset>

                </form>
            </div>
        </div>
    </div>
</div>
