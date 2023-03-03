<form id="<?=$rootId;?>" method="POST" action="<?= $ajax; ?>">
    <div class="column input-group ml-3">
        <div class="datepicker-limit">
            <select id="<?=$filterId;?>" class="form-control" 
                @change="selectFilterEntity($event)"
            >
                <option v-for="(option, index) in modes" :value="index">{{ i18n[option.title] }}</option>
            </select>
        </div>
        <div class='ml-3 datepicker-limit input-group date'>
            <input id="<?=$fromDatePickerId;?>" type='text' class="form-control" 
                value="<?= $value ?? '' ?>"
                <?= !!$onDateChange ? "@change='{$onDateChange}'" : "" ?>
            />
            <div class="input-group-append">
                <span class="input-group-text">
                    <i class="bi bi-calendar3"></i>
                </span>
            </div>
        </div>

        <span class="ml-2 mr-2" :class="{'hidden': !picker.diapazone, 'mdash': picker.diapazone}">
            <i class="bi bi-dash-lg"></i>
        </span>

        <div 
            id="<?=$toDatePickerId?>Picker"
            :class='{"datepicker-limit": true, "date": true, "input-group": true, "hidden": !picker.diapazone}'
        >
            <input
                id="<?=$toDatePickerId;?>"
                type='text'
                class="form-control"
                <?= !!$onDateChange ? "@change='{$onDateChange}'" : "" ?> />
            <div class="input-group-append">
                <span class="input-group-text">
                    <i class="bi bi-calendar3"></i>
                </span>
            </div>
        </div>
        <span v-if="awaiting" role="status" aria-hidden="true" class="spinner-border status-spiner text-primary ml-2"></span>
    </div>
</form>