<?= $this->Html->script('notification_settings'); ?>

<div class="row ml-3">
    <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
        <div class="form-group">
            <label for="notificationCreateTicket"><?= __('Business process when creating a ticket') ?></label>
            <select class="custom-select" id="notificationCreateTicket">
                <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                    <option value="<?= $id ?>" <?= (isset($options['notificationCreateTicket']) && $id == $options['notificationCreateTicket']) ? 'selected' : '' ?>><?= $templateName ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="notificationChangeTicketStatus"><?= __('Business process when changing ticket status') ?></label>
            <select class="custom-select" id="notificationChangeTicketStatus">
                <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                    <option value="<?= $id ?>" <?= (isset($options['notificationChangeTicketStatus']) && $id == $options['notificationChangeTicketStatus']) ? 'selected' : '' ?>><?= $templateName ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="notificationReceivingCustomerResponse"><?= __('Business process when receiving a customer response') ?></label>
            <select class="custom-select" id="notificationReceivingCustomerResponse">
                <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                    <option value="<?= $id ?>" <?= (isset($options['notificationReceivingCustomerResponse']) && $id == $options['notificationReceivingCustomerResponse']) ? 'selected' : '' ?>><?= $templateName ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="notificationChangeResponsible"><?= __('Business process when changing the responsible') ?></label>
            <select class="custom-select" id="notificationChangeResponsible">
                <?php foreach($arContactWorkflowTemplates as $id => $templateName): ?>
                    <option value="<?= $id ?>" <?= (isset($options['notificationChangeResponsible']) && $id == $options['notificationChangeResponsible']) ? 'selected' : '' ?>><?= $templateName ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button id="saveNotificationsSettings" type="button" name="saveNotificationsSettings" class="btn btn-primary">
            <span id="notificationSpinner" role="status" aria-hidden="true" class="spinner-border spinner-border-sm mr-2 hidden"></span>
            <?= __('Save') ?></button>
    </form>
</div>
