<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
// vim: set ai ts=4 sw=4 ft=phtml:

?>

<div class="fpbx-container container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="display no-border">
                <h1><?php echo _("Device SCCP Phone") ?></h1>
                <div id="toolbar-sccp-phone">
                    <a class="btn btn-default" href="config.php?display=sccp_phone&tech_hardware=cisco"><i class="fa fa-plus">&nbsp;</i><?php echo _("Add Device Phone") ?></a>
                    <button type="button" id="btn-exchange-sep" class="btn btn-default" data-toggle="modal" data-target="#modal-swap-sep">
                        <i class="fa fa-exchange">&nbsp;</i><span><?php echo _('Exchange SEP') ?></span>
                    </button>
                    <button type="button" id="btn-assign-firmware" class="btn btn-default btn-tab-select" data-toggle="modal" data-target="#modal-assign-firmware" disabled>
                        <i class="fa fa-download">&nbsp;</i><span><?php echo _('Assign Firmware') ?></span>
                    </button>
                    <button id="remove-sccp-phone" class="btn btn-danger sccp_update btn-tab-select" data-id="delete_hardware" disabled>
                        <i class="glyphicon glyphicon-remove"></i> <span><?php echo _('Delete') ?></span>
                    </button>
                    <button name="cr_sccp_phone_xml" class="btn sccp_update btn-default" data-id="create-cnf">
                        <i class="glyphicon glyphicon-ok"></i> <span><?php echo _('Create CNF') ?></span>
                    </button>
                    <button name="update_button_label" class="btn sccp_update btn-default" data-id="update_button_label">
                        <i class="glyphicon glyphicon-ok"></i> <span><?php echo _('Update Button label') ?></span>
                    </button>
                    <button name="reset_sccp_phone" class="btn sccp_update btn-default" data-id="reset_dev">
                        <i class="glyphicon glyphicon-ok"></i> <span><?php echo _('Reset Device') ?></span>
                    </button>
                    <button name="reset_sccp_token" class="btn sccp_update btn-default" data-id="reset_token">
                        <i class="glyphicon glyphicon-ok"></i> <span><?php echo _('Reset Token Device') ?></span>
                    </button>
                </div>
                <table data-cookie="true" data-cookie-id-table="sccp-phone" data-url="ajax.php?module=sccp_manager&command=getPhoneGrid&type=sccp"
                            data-cache="false" data-show-refresh="true" data-toolbar="#toolbar-sccp" data-maintain-selected="true"
                            data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true"
                            data-search="true" class="table table-striped ext-list" id="table-sccp" data-id="name" data-unique-id="name">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-sortable="true" data-field="name"><?php echo _('Device ID') ?></th>
                            <th data-sortable="true" data-field="description"><?php echo _('Device  Description') ?></th>
                            <th data-sortable="true" data-formatter="DispayTypeFormatter" data-field="type"><?php echo _('Device type') ?></th>
                            <th data-sortable="true" data-field="button" data-formatter="LineFormatter"><?php echo _('Line') ?></th>
                            <th data-sortable="true" data-field="status"><?php echo _('Status') ?></th>
                            <th data-sortable="true" data-field="firmware_assigned"><?php echo _('Assigned Firmware') ?></th>
                            <th data-sortable="true" data-field="firmware_loaded" data-formatter="FirmwareLoadedFormatter"><?php echo _('Loaded Firmware') ?></th>
                            <th data-sortable="true" data-field="firmware_status" data-formatter="FirmwareStatusFormatter"><?php echo _('FW Status') ?></th>
                            <th data-sortable="true" data-field="address"><?php echo _('Address') ?></th>
                            <th data-field="actions" data-formatter="DispayDeviceActionsKeyFormatter"><?php echo _('Actions') ?></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function DispayTypeFormatter(value, row, index) {
        var exp_model = value;
        if (row['addon'] !== null ) {
            var posd = row['addon'].indexOf(';');
            if (posd >0) {
                exp_model += ' + 2x ' + row['addon'].substring(0, posd);
            } else {
                exp_model += ' + ' + row['addon'];
            }
        }
        return  exp_model;

    }
    function DispayDeviceActionsKeyFormatter(value, row, index) {
        var exp_model = '';
        if (row['new_hw'] == "Y") {
            exp_model += '<a href="?display=sccp_phone&tech_hardware=cisco&new_id=' + row['name'] + '&type='+ row['type'];
            if (row['addon'] !== null ) {
                exp_model += '&addon='+ row['addon'];
            }
            exp_model += '"><i class="fa fa-pencil"></i></a> &nbsp; &nbsp;\n';

        } else {
            exp_model += '<a href="?display=sccp_phone&tech_hardware=cisco&id=' + row['name'] + '"><i class="fa fa-pencil"></i></a> &nbsp; &nbsp;\n';
            exp_model += '</a> &nbsp;<a class="btn-item-delete" data-for="hardware" data-id="' + row['name'] + '"><i class="fa fa-trash"></i></a>';
        }
        return  exp_model;
    }
    function LineFormatter(value, row, index) {
        if (value === null)  {
            return  '-- EMPTY --';
        }
        var data = value.split(";");
        result = '';
        for (var i = 0; i < data.length; i++) {
            var val = data[i].split(',');
            if (val[0] === 'line') {
              result = result + val[1] + '<br>';
            }
        }
        return  result;
    }
    function FirmwareLoadedFormatter(value, row, index) {
        if (row.firmware_status === 'loading') {
            return '<i class="fa fa-spinner fa-spin text-muted" title="<?php echo _('Checking firmware...'); ?>"></i>';
        }
        return value || '';
    }
    function FirmwareStatusFormatter(value, row, index) {
        var status = row.firmware_status || 'offline';
        var title = status;
        var icon = 'fa-minus-circle text-muted';
        if (status === 'ok') {
            icon = 'fa-check-circle text-success';
            title = '<?php echo _('Firmware up to date'); ?>';
        } else if (status === 'pending') {
            icon = 'fa-exclamation-circle text-warning';
            title = '<?php echo _('Update pending'); ?>';
        } else if (status === 'loading') {
            icon = 'fa-spinner fa-spin text-muted';
            title = '<?php echo _('Checking firmware...'); ?>';
        } else if (status === 'offline') {
            icon = 'fa-minus-circle text-muted';
            title = '<?php echo _('Device offline'); ?>';
        } else if (status === 'unknown') {
            icon = 'fa-question-circle text-muted';
            title = '<?php echo _('Unknown'); ?>';
        }
        return '<i class="fa ' + icon + '" title="' + title + '"></i>';
    }

    function refreshPhoneFirmwareStatus(rows) {
        var devices = (rows || []).filter(function (row) {
            return row && row.name && row.firmware_status === 'loading';
        });
        if (!devices.length) {
            return;
        }
        var payload = devices.map(function (row) {
            return {
                name: row.name,
                firmware_assigned: row.firmware_assigned || ''
            };
        });
        $.ajax({
            url: 'ajax.php?module=sccp_manager&command=get_phone_firmware_status',
            method: 'POST',
            data: {
                devices: JSON.stringify(payload)
            },
            success: function (resp) {
                if (!resp || !resp.status || !resp.devices) {
                    return;
                }
                Object.keys(resp.devices).forEach(function (sepId) {
                    var fw = resp.devices[sepId];
                    $('#table-sccp').bootstrapTable('updateByUniqueId', {
                        id: sepId,
                        row: {
                            firmware_loaded: fw.firmware_loaded,
                            firmware_status: fw.firmware_status
                        }
                    });
                });
            }
        });
    }

    $('#table-sccp').on('load-success.bs.table', function (e, data) {
        refreshPhoneFirmwareStatus(data);
    });

</script>

<div class="modal fade" id="modal-swap-sep" tabindex="-1" role="dialog" aria-labelledby="swapSepLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="swapSepLabel"><?php echo _('Exchange SEP / Replace Phone'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _('Close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills nav-justified" id="swap-sep-steps">
                    <li class="active"><a href="#swap-step-mode" data-toggle="tab"><?php echo _('Mode'); ?></a></li>
                    <li><a href="#swap-step-devices" data-toggle="tab"><?php echo _('Devices'); ?></a></li>
                    <li><a href="#swap-step-preview" data-toggle="tab"><?php echo _('Preview'); ?></a></li>
                </ul>
                <div class="tab-content" style="margin-top: 15px;">
                    <div class="tab-pane active" id="swap-step-mode">
                        <div class="form-group">
                            <label class="radio-inline">
                                <input type="radio" name="swap_mode" value="swap" checked>
                                <?php echo _('Swap two existing devices'); ?>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="radio-inline">
                                <input type="radio" name="swap_mode" value="replace">
                                <?php echo _('Replace existing device with a new one'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="tab-pane" id="swap-step-devices">
                        <div class="form-group">
                            <label for="swap-source"><?php echo _('Source device (configuration donor)'); ?></label>
                            <select class="form-control" id="swap-source"></select>
                        </div>
                        <div class="form-group" id="swap-target-existing-group">
                            <label for="swap-target-existing"><?php echo _('Target device'); ?></label>
                            <select class="form-control" id="swap-target-existing"></select>
                        </div>
                        <div id="swap-target-replace-group" style="display:none;">
                            <div class="form-group">
                                <label for="swap-target-new"><?php echo _('New / discovered device'); ?></label>
                                <select class="form-control" id="swap-target-new">
                                    <option value=""><?php echo _('— Select discovered device or enter MAC below —'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="swap-target-mac"><?php echo _('Or enter MAC address'); ?></label>
                                <input type="text" class="form-control" id="swap-target-mac" placeholder="001122334455">
                            </div>
                            <div class="form-group">
                                <label><?php echo _('Old device after replacement'); ?></label>
                                <div>
                                    <label class="radio-inline">
                                        <input type="radio" name="swap_old_action" value="keep" checked>
                                        <?php echo _('Keep unchanged'); ?>
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" name="swap_old_action" value="delete">
                                        <?php echo _('Delete'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="swap-step-preview">
                        <div id="swap-preview-summary" class="well well-sm"></div>
                        <div id="swap-preview-warnings" class="alert alert-warning" style="display:none;"></div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="swap-confirm-understand">
                                <?php echo _('I understand the impact of this operation'); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="swap-prev-step" style="display:none;"><?php echo _('Back'); ?></button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="swap-next-step"><?php echo _('Next'); ?></button>
                <button type="button" class="btn btn-danger" id="swap-execute" style="display:none;" disabled><?php echo _('Execute exchange'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-assign-firmware" tabindex="-1" role="dialog" aria-labelledby="assignFirmwareLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="assignFirmwareLabel"><?php echo _('Assign Firmware'); ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _('Close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills nav-justified" id="firmware-steps">
                    <li class="active"><a href="#fw-step-devices" data-toggle="tab"><?php echo _('Devices'); ?></a></li>
                    <li><a href="#fw-step-firmware" data-toggle="tab"><?php echo _('Firmware'); ?></a></li>
                    <li><a href="#fw-step-preview" data-toggle="tab"><?php echo _('Preview'); ?></a></li>
                </ul>
                <div class="tab-content" style="margin-top: 15px;">
                    <div class="tab-pane active" id="fw-step-devices">
                        <p><?php echo _('Selected devices will receive the firmware version chosen per phone model.'); ?></p>
                        <div id="fw-device-list" class="well well-sm"></div>
                    </div>
                    <div class="tab-pane" id="fw-step-firmware">
                        <div id="fw-model-selectors"></div>
                        <p class="help-block"><?php echo _('Choose Model default to clear per-device overrides and use the model firmware.'); ?></p>
                    </div>
                    <div class="tab-pane" id="fw-step-preview">
                        <div class="table-responsive">
                            <table class="table table-striped" id="fw-preview-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _('Device'); ?></th>
                                        <th><?php echo _('Type'); ?></th>
                                        <th><?php echo _('Current'); ?></th>
                                        <th><?php echo _('New'); ?></th>
                                        <th><?php echo _('Valid'); ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="fw-preview-warnings" class="alert alert-warning" style="display:none;"></div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="fw-trigger-reset" checked>
                                <?php echo _('Send SCCP reset after assignment (asterisk: sccp reset)'); ?>
                            </label>
                        </div>
                        <p class="help-block" id="fw-reset-help"><?php echo _('If unchecked, only the database and SEP XML are updated. The phone will use the new firmware on its next reboot or manual reset.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="fw-prev-step" style="display:none;"><?php echo _('Back'); ?></button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="fw-next-step"><?php echo _('Next'); ?></button>
                <button type="button" class="btn btn-danger" id="fw-execute" style="display:none;"><?php echo _('Assign and reset'); ?></button>
            </div>
        </div>
    </div>
</div>
