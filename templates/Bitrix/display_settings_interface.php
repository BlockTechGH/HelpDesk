<?= $this->Html->script('fit_window'); ?>
<div id="setting_container" class="row">
    <div class="col">
        <form method="POST" action="<?= $this->Url->build(['_name' => 'oc_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
            <div class="form-group">
                <label for="widgetName"><?= __('Widget title') ?></label>
                <input type="text" class="form-control <?= (isset($errors['widget_name'])) ? 'border border-danger' : '' ?>"
                       id="widgetName" name="widgetName" value="<?=$widgetName?>" aria-describedby="widgetTitleHelp"
                />
            </div>
            <div class="form-group">
                <label for="phoneNumber"><?= __('Phone number') ?></label>
                <input type="tel" class="form-control <?= (isset($errors['phone_number'])) ? 'border border-danger' : '' ?>"
                       id="phoneNumber" name="phoneNumber" value="<?=$phoneNumber?>"
                />
            </div>
            <div class="form-group">
                <label for="apiKey"><?= __('API Key') ?></label>
                <input type="text" class="form-control <?= (isset($errors['api_key'])) ? 'border border-danger' : '' ?>"
                       id="apiKey" name="apiKey" value="<?=$apiKey?>" aria-describedby="keyHelp"
                />
                <small id="keyHelp" class="form-text text-muted"><?= __('Copy your Kaleyra API key here.') ?></small>
            </div>
            <div class="form-group">
                <label for="sid"><?= __('SID') ?></label>
                <input type="text" class="form-control <?= (isset($errors['sid'])) ? 'border border-danger' : '' ?>"
                       id="sid" name="sid" value="<?=$sid?>" aria-describedby="sidHelp"
                />
                <small id="sidHelp" class="form-text text-muted"><?= __('Copy your Kaleyra SID here.') ?></small>
            </div>
            <div class="alert alert-info">
                <?= __('Please specify the following url - {0} in the settings of the whatsapp number.<br/>
Channels -> WhatsApp -> Configurations -> Edit the number -> Add the end point in incoming URL', [$callbackURL]) ?>
            </div>

            <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
            <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
            <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
            <input type="hidden" name="member_id" value="<?=$memberId?>" />
            <input type="hidden" name="PLACEMENT_OPTIONS" value='<?=$options?>' />

            <button type="submit" name="saveSettings" class="btn btn-primary"><?= __('Save') ?></button>
        </form>
    </div>
</div>
