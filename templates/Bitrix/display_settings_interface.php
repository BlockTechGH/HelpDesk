<?= $this->Html->script('fit_window'); ?>
<div id="setting_container" class="row">
    <div class="col-2">
        <div class="nav flex-column nav-pills" id="myTab" role="tablist" aria-orientation="vertical">
            <button class="nav-link active" data-toggle="tab" type="button" role="tab" 
                aria-selected="true"
                id="sources-tab" 
                data-target="#sources" 
                aria-controls="sources"
            >
                <?=__('Sources');?>
            </button>
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
                id="statuses-tab" 
                data-target="#statuses" 
                aria-controls="statuses"
            >
                <?=__('Statuses');?>
            </button>
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
                id="statuses-tab" 
                data-target="#statuses" 
                aria-controls="statuses"
            >
                <?=__('Categories');?>
            </button>
        </div>
    </div>
    <div class="col-10">
        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="sources" role="tabpanel" aria-labelledby="sources-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <div class="form-group">
                        <label for="sources_on_email"><?=__('Create ticket by e-mail');?></label>
                        <input type="checkbox"
                            id="sources_on_email" 
                            name="sources_on_email"
                            <?=$options['sources_on_email'] ? 'checked' : '';?>/>
                    </div>
                    <div class="form-group">
                        <label for="sources_on_open_channel"><?=__('Create ticket by chat via Open Channel');?></label>
                        <input type="checkbox"
                            id="sources_on_open_channel" 
                            name="sources_on_open_channel"
                            <?=$options['sources_on_open_channel'] ? 'checked' : '';?>/>
                    </div>
                    <div class="form-group">
                        <label for="sources_on_phone_calls"><?=__('Create ticket by phone call');?></label>
                        <input type="checkbox"
                            id="sources_on_phone_calls" 
                            name="sources_on_phone_calls"
                            <?=$options['sources_on_phone_calls'] ? 'checked' : '';?>/>
                    </div>

                    <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
                    <input type="hidden" name="member_id" value="<?=$memberId?>" />

                    <button type="submit" name="saveSettings" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
            <div class="tab-pane fade show" id="statuses" role="tabpanel" aria-labelledby="statuses-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
                    <input type="hidden" name="member_id" value="<?=$memberId?>" />

                    <button type="submit" name="saveStatuses" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
            <div class="tab-pane fade show" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
                    <input type="hidden" name="member_id" value="<?=$memberId?>" />

                    <button type="submit" name="saveCategories" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
