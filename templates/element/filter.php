<form id="<?=$rootId;?>" method="POST" action="<?= $ajax; ?>">
    <div class="column input-group ml-3">
        <div class="datepicker-limit">
            <select id="<?=$filterId;?>" class="form-control" 
                @change="selectFilterEntity($event)"
            >
                <option v-for="(option, index) in modes" :value="index">{{ i18n[option.title] }}</option>
            </select>
        </div>
        <div class='ml-3 datepicker-limit form-input date'>
            <input id="<?=$fromDatePickerId;?>" type='text' class="form-control" 
                value="<?= $value ?? '' ?>"
                <?= !!$onDateChange ? "@change='{$onDateChange}'" : "" ?> />
            <span class="input-group-addon">
                <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
            </span>
        </div>
        <span :class="{'m-2': true, 'hidden': !picker.diapazone}">&mdash;</span>
        <div 
            id="<?=$toDatePickerId?>Picker"
            :class='{"datepicker-limit": true, "date": true, "form-input": true, "hidden": !picker.diapazone}'
        >
            <input
                id="<?=$toDatePickerId;?>"
                type='text'
                class="form-control"
                <?= !!$onDateChange ? "@change='{$onDateChange}'" : "" ?> />
            <span class="input-group-addon">
                <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
            </span>
        </div>
        <span v-if="awaiting" role="status" aria-hidden="true" class="spinner-border status-spiner text-primary ml-2"></span>
    </div>
</form>