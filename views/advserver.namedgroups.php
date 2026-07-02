<?php
/*
 * SCCP Named Groups Management
 * Allows creation, editing, and deletion of Named Call/Pickup Groups
 */
?>
<form autocomplete="off" name="frm_namedgroups" id="frm_namedgroups" class="fpbx-submit" action="" method="post">
    <input type="hidden" name="category" value="namedgroupsform">
    <input type="hidden" name="Submit" value="Submit">
    <div class="fpbx-container container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="display no-border">
                    <div id="toolbar-namedgroups">
                        <button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#modal_add_namedgroup">
                            <i class="fa fa-plus"></i> <?php echo _("Add Group"); ?>
                        </button>
                    </div>
                    <table data-cookie="true" data-cookie-id-table="sccp_namedgroups-all" 
                           data-url="ajax.php?module=sccp_manager&command=getNamedGroups" 
                           data-cache="false" data-show-refresh="true" data-toolbar="#toolbar-namedgroups" 
                           data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" 
                           data-toggle="table" data-pagination="true" data-search="true" 
                           class="table table-striped ext-list" id="namedgroups-all" data-unique-id="id">
                        <thead>
                            <tr>
                                <th data-sortable="true" data-field="id"><?php echo _('ID')?></th>
                                <th data-sortable="true" data-field="groupname"><?php echo _('Group Name')?></th>
                                <th data-sortable="false" data-field="grouptype"><?php echo _('Type')?></th>
                                <th data-sortable="false" data-field="usage_count"><?php echo _('In Use')?></th>
                                <th data-sortable="false" data-field="description"><?php echo _('Description')?></th>
                                <th data-field="actions" data-formatter="DisplayActionsGroupFormatter"><?php echo _('Actions')?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal Add/Edit Group -->
<div class="modal fade" id="modal_add_namedgroup" tabindex="-1" role="dialog" aria-labelledby="addGroupLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGroupLabel"><?php echo _("Add Named Group"); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form_add_group">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="group_name"><?php echo _("Group Name"); ?> <span class="required">*</span></label>
                        <input type="text" class="form-control" id="group_name" name="groupname" placeholder="e.g., sales, support, reception" required>
                        <small class="form-text text-muted"><?php echo _("Unique name for this group"); ?></small>
                    </div>
                    <div class="form-group">
                        <label for="group_type"><?php echo _("Group Type"); ?></label>
                        <select class="form-control" id="group_type" name="grouptype">
                            <option value="callgroup">Call Group</option>
                            <option value="pickupgroup">Pickup Group</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="group_description"><?php echo _("Description"); ?></label>
                        <textarea class="form-control" id="group_description" name="description" rows="3" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo _("Cancel"); ?></button>
                    <button type="submit" class="btn btn-primary" id="btn_save_group"><?php echo _("Save Group"); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Group -->
<div class="modal fade" id="modal_edit_namedgroup" tabindex="-1" role="dialog" aria-labelledby="editGroupLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupLabel"><?php echo _("Edit Named Group"); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form_edit_group">
                <input type="hidden" id="edit_group_id" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_group_name"><?php echo _("Group Name"); ?> <span class="required">*</span></label>
                        <input type="text" class="form-control" id="edit_group_name" name="groupname" placeholder="e.g., sales, support, reception" required>
                        <small class="form-text text-muted"><?php echo _("Unique name for this group"); ?></small>
                    </div>
                    <div class="form-group">
                        <label for="edit_group_type"><?php echo _("Group Type"); ?></label>
                        <select class="form-control" id="edit_group_type" name="grouptype">
                            <option value="callgroup">Call Group</option>
                            <option value="pickupgroup">Pickup Group</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_group_description"><?php echo _("Description"); ?></label>
                        <textarea class="form-control" id="edit_group_description" name="description" rows="3" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo _("Cancel"); ?></button>
                    <button type="submit" class="btn btn-primary" id="btn_save_edit_group"><?php echo _("Update Group"); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add group form submission
    $('#form_add_group').on('submit', function(e) {
        e.preventDefault();
        var groupname = $('#group_name').val();
        var grouptype = $('#group_type').val();
        var description = $('#group_description').val();
        
        if (!groupname) {
            alert('<?php echo _("Group name is required"); ?>');
            return false;
        }
        
        $.ajax({
            url: 'ajax.php?module=sccp_manager&command=addNamedGroup',
            type: 'POST',
            data: {
                groupname: groupname,
                grouptype: grouptype,
                description: description
            },
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    $('#modal_add_namedgroup').modal('hide');
                    $('#namedgroups-all').bootstrapTable('refresh');
                    $('#form_add_group')[0].reset();
                    $.notify({message: response.message}, {type: 'success'});
                } else {
                    $.notify({message: response.message || '<?php echo _("Error adding group"); ?>'}, {type: 'danger'});
                }
            },
            error: function() {
                $.notify({message: '<?php echo _("Error adding group"); ?>'}, {type: 'danger'});
            }
        });
    });

    // Edit group form submission
    $('#form_edit_group').on('submit', function(e) {
        e.preventDefault();
        var id = $('#edit_group_id').val();
        var groupname = $('#edit_group_name').val();
        var grouptype = $('#edit_group_type').val();
        var description = $('#edit_group_description').val();
        
        if (!groupname || !id) {
            alert('<?php echo _("Group name and ID are required"); ?>');
            return false;
        }
        
        $.ajax({
            url: 'ajax.php?module=sccp_manager&command=updateNamedGroup',
            type: 'POST',
            data: {
                id: id,
                groupname: groupname,
                grouptype: grouptype,
                description: description
            },
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    $('#modal_edit_namedgroup').modal('hide');
                    $('#namedgroups-all').bootstrapTable('refresh');
                    $.notify({message: response.message}, {type: 'success'});
                } else {
                    $.notify({message: response.message || '<?php echo _("Error updating group"); ?>'}, {type: 'danger'});
                }
            },
            error: function() {
                $.notify({message: '<?php echo _("Error updating group"); ?>'}, {type: 'danger'});
            }
        });
    });

    // Reset forms when modal is hidden
    $('#modal_add_namedgroup').on('hidden.bs.modal', function() {
        $('#form_add_group')[0].reset();
    });
});

// Action formatter for table
function DisplayActionsGroupFormatter(value, row, index) {
    return [
        '<a class="btn btn-xs btn-warning" href="javascript:void(0)" title="Edit" onclick="editNamedGroup(' + row.id + ', \'' + row.groupname.replace(/'/g, "\\'") + '\', \'' + row.grouptype + '\', \'' + (row.description || '').replace(/'/g, "\\'") + '\')">',
        '<i class="fa fa-pencil"></i>',
        '</a>  ',
        '<a class="btn btn-xs btn-danger" href="javascript:void(0)" title="Delete" onclick="deleteNamedGroup(' + row.id + ', \'' + row.groupname.replace(/'/g, "\\'") + '\')">',
        '<i class="fa fa-trash"></i>',
        '</a>'
    ].join('')
}

function editNamedGroup(id, groupname, grouptype, description) {
    $('#edit_group_id').val(id);
    $('#edit_group_name').val(groupname);
    $('#edit_group_type').val(grouptype);
    $('#edit_group_description').val(description);
    $('#modal_edit_namedgroup').modal('show');
}

function deleteNamedGroup(id, groupname) {
    if (confirm('<?php echo _("Are you sure you want to delete this group?"); ?> (' + groupname + ')')) {
        $.ajax({
            url: 'ajax.php?module=sccp_manager&command=deleteNamedGroup',
            type: 'POST',
            data: { id: id, groupname: groupname },
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    $('#namedgroups-all').bootstrapTable('refresh');
                    $.notify({message: response.message}, {type: 'success'});
                } else {
                    $.notify({message: response.message || '<?php echo _("Error deleting group"); ?>'}, {type: 'danger'});
                }
            },
            error: function() {
                $.notify({message: '<?php echo _("Error deleting group"); ?>'}, {type: 'danger'});
            }
        });
    }
}
</script>
