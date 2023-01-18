<?= $this->Html->script('sla_settings', ['block' => true]); ?>
<?= $this->Html->css('sla_settings', ['block' => true]); ?>

<?php
$arSlaSettings = new stdClass();
$serializeSlaSettings = $options['sla_settings'] ?? false;
if($serializeSlaSettings)
{
    $arSlaSettings = unserialize($serializeSlaSettings);
    foreach($arSlaSettings as $i => $department)
    {
        if(count($department['initialNotificationUsers']) === 0)
        {
            $arSlaSettings[$i]['initialNotificationUsers'] = new stdClass();
        }
        if(count($department['subsequentNotificationUsers']) === 0)
        {
            $arSlaSettings[$i]['subsequentNotificationUsers'] = new stdClass();
        }
    }
}
?>
<script type="text/javascript">
    window.slaData = {
        arDepartments: <?= json_encode($arDepartments) ?>,
        arContactWorkflowTemplates: <?= json_encode($arContactWorkflowTemplates) ?>,
        arCompanyWorkflowTemplates: <?= json_encode($arCompanyWorkflowTemplates) ?>,
        departmentsConfigs: <?= json_encode($arSlaSettings) ?>,
        selectedDepartment: 0,
        ajax: "<?= $this->Url->build([
            '_name' => 'crm_settings_interface',
            '?' => ['DOMAIN' => $domain]
        ]); ?>",
        required: <?= json_encode($required) ?>,
        awaiting: false,
        langPhrases: Object.freeze(
        {
            add: '<?= __('Add') ?>',
            delete: '<?= __('Delete') ?>',
            initRespTimeKPI: '<?= __('Initial response time KPI (minutes)') ?>',
            sendNotificationTo: '<?= __('Send notification to') ?>',
            subRespTimeKPI: '<?= __('Subsequent response time KPI (minutes)') ?>',
            contactTemplate: '<?= __('Workflow template for notifications (Contact level)') ?>',
            companyTemplate: '<?= __('Workflow template for notifications (Company level)') ?>',
            saveSuccess: '<?= __('Settings for SLA saved successfully') ?>',
            saveError: '<?= __('An error occurred while saving SLA settings') ?>'
        })
    };
</script>

<div class="row ml-3">
    <form class="col-10">
        <h2 class="display-6"><?= __('SLA settings') ?></h2>

        <div id="slaApplication">
        <!-- Start Application -->

            <department-config
                v-for="config, index in departmentsConfigs"
                v-bind:key="'depconfig' + index"
                v-bind:config="config"
                v-bind:lang="langPhrases"
                v-bind:contact-templates="arContactWorkflowTemplates"
                v-bind:company-templates="arCompanyWorkflowTemplates"
                v-on:delete-department-block="deleteDepartmentBlock"
                v-on:add-users-to-config="addUsersToConfig"
                v-on:delete-user-from-config="deleteUserFromConfig"
            >
            </department-config>

            <div class="sla-panel">
                <div class="row ml-3">
                    <div class="col-10 ml-3 pt-2 pb-2">
                        <div class="form-inline justify-content-center">
                            <label class="my-1 mr-2" for="departmentList"><?= __('Department list') ?></label>
                            <select id="departmentList" class="custom-select my-1 mr-sm-2" v-model="selectedDepartment">
                                <option
                                    v-for="(department, index) in arDepartments"
                                    :value="index"
                                >
                                    {{ department }}
                                </option>
                            </select>
                            <button type="button" name="deleteBlock" class="btn btn-success my-1" v-on:click="addDepartment" v-bind:disabled="isAddButtonDisabled">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
                                  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                  <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                </svg>
                                <?= __('Add') ?>
                            </button>

                            <button id="saveSlaSettings" type="button" name="saveSlaSettings" class="btn btn-primary ml-5" v-on:click="saveSlaSettings">
                                <span id="slaSpinner" role="status" aria-hidden="true" class="spinner-border spinner-border-sm mr-2" v-if="awaiting"></span>
                                <?= __('Save') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <!-- End aApplication -->
        </div>
    </form>
</div>
