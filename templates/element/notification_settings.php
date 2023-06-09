<?= $this->Html->script('notification_settings', ['block' => true]); ?>

<div class="row ml-3">
    <form method="POST" class="col-10" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
        <!-- START CONTACT -->
        <div class="jumbotron p2">
            <h2 class="display-6"><?= __('Contact settings') ?></h2>

            <div class="form-group">
                <label for="notificationCreateTicketContact"><?= __('Business process when creating a ticket') ?></label>
                <select class="custom-select" id="notificationCreateTicketContact">
                    <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationCreateTicketContact']) && $id == $options['notificationCreateTicketContact']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationChangeTicketStatusContact"><?= __('Business process when changing ticket status') ?></label>
                <select class="custom-select" id="notificationChangeTicketStatusContact">
                    <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationChangeTicketStatusContact']) && $id == $options['notificationChangeTicketStatusContact']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationReceivingCustomerResponseContact"><?= __('Business process when receiving a customer response') ?></label>
                <select class="custom-select" id="notificationReceivingCustomerResponseContact">
                    <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationReceivingCustomerResponseContact']) && $id == $options['notificationReceivingCustomerResponseContact']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationChangeResponsibleContact"><?= __('Business process when changing the responsible') ?></label>
                <select class="custom-select" id="notificationChangeResponsibleContact">
                    <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationChangeResponsibleContact']) && $id == $options['notificationChangeResponsibleContact']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationResolutionAddedContact"><?= __('Business process when resolution added') ?></label>
                <select class="custom-select" id="notificationResolutionAddedContact">
                    <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationResolutionAddedContact']) && $id == $options['notificationResolutionAddedContact']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- END CONTACT -->

        <!-- START COMPANY -->
        <div class="jumbotron p2">
            <h2 class="display-6"><?= __('Company settings') ?></h2>

            <div class="form-group">
                <label for="notificationCreateTicketCompany"><?= __('Business process when creating a ticket') ?></label>
                <select class="custom-select" id="notificationCreateTicketCompany">
                    <?php foreach($arCompanyWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationCreateTicketCompany']) && $id == $options['notificationCreateTicketCompany']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationChangeTicketStatusCompany"><?= __('Business process when changing ticket status') ?></label>
                <select class="custom-select" id="notificationChangeTicketStatusCompany">
                    <?php foreach($arCompanyWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationChangeTicketStatusCompany']) && $id == $options['notificationChangeTicketStatusCompany']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationReceivingCustomerResponseCompany"><?= __('Business process when receiving a customer response') ?></label>
                <select class="custom-select" id="notificationReceivingCustomerResponseCompany">
                    <?php foreach($arCompanyWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationReceivingCustomerResponseCompany']) && $id == $options['notificationReceivingCustomerResponseCompany']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationChangeResponsibleCompany"><?= __('Business process when changing the responsible') ?></label>
                <select class="custom-select" id="notificationChangeResponsibleCompany">
                    <?php foreach($arCompanyWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationChangeResponsibleCompany']) && $id == $options['notificationChangeResponsibleCompany']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notificationResolutionAddedCompany"><?= __('Business process when resolution added') ?></label>
                <select class="custom-select" id="notificationResolutionAddedCompany">
                    <?php foreach($arCompanyWorkflowTemplates as $id => $templateName): ?>
                        <option value="<?= $id ?>" <?= (isset($options['notificationResolutionAddedCompany']) && $id == $options['notificationResolutionAddedCompany']) ? 'selected' : '' ?>><?= $templateName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- END COMPANY -->

        <button id="saveNotificationsSettings" type="button" name="saveNotificationsSettings" class="btn btn-primary">
            <span id="notificationSpinner" role="status" aria-hidden="true" class="spinner-border spinner-border-sm mr-2 hidden"></span>
            <?= __('Save') ?>
        </button>
    </form>
</div>
