<form id="<?=$rootId;?>" method="POST" action="<?= $ajax; ?>">
    <div class="column input-group">
        <div class="datepicker-limit">
            <select id="<?=$filterId;?>" class="form-control" 
                @change="selectFilterEntity($event)"
            >
                <option v-for="(option, index) in modes" :value="index">{{ i18n[option.title] }}</option>
            </select>
        </div>
        <div class='ml-3 datepicker-limit form-input date'>
            <input id="<?=$fromDatePickerId;?>" type='text' class="form-control" 
                value="<?=date('m/y')?>" 
                <?= !!$onDateChange ? "@change='{$onDateChange}'" : "" ?> />
            <span class="input-group-addon">
                <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
            </span>
        </div>
        <span :class="{'m-2': true, 'fade': true, 'show': picker.diapazone}">&mdash;</span>
        <div 
            id="<?=$toDatePickerId?>Picker"
            :class='{"datepicker-limit": true, "date": true, "form-input": true, "fade": true, "show": picker.diapazone}' 
        >
            <input
                id="<?=$toDatePickerId;?>"
                :disabled="!picker.diapazone"
                type='text'
                class="form-control"
                <?= !!$onDateChange ? "@change='{$onDateChange}'" : "" ?> />
            <span class="input-group-addon">
                <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
            </span>
        </div>
    </div>
</form>