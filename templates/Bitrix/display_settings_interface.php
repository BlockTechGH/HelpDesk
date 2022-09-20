<?= $this->Html->script('fit_window'); ?>
<div id="setting_container" class="row">
    <div class="col-2">
        <div class="nav flex-column nav-pills" id="myTab" role="tablist" aria-orientation="vertical">
            <button class="nav-link active" data-toggle="tab" type="button" role="tab" 
                aria-selected="true"
                id="options-tab" 
                data-target="#options" 
                aria-controls="options"
            >
                <?=__('Options');?>
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
            <div class="tab-pane fade show active" id="options" role="tabpanel" aria-labelledby="options-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <?php foreach($options as $option): ?>
                        <div class="form-group">
                            <label for="<?=$option['opt'];?>"><?=$option['opt'];?></label>
                            <input class="form-control" name="<?=$option['opt'];?>" value="<?=$option['value'];?>"/>
                            <button type="button" class="btn <?=$option['active'] ? 'btn-first' : 'btn-second';?>"><?=$option['active'] ? __('Delete') : __('Restore');?></button>
                        </div>
                    <?php endforeach;?>

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

                    <button type="submit" name="saveSettings" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
