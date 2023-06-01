<div class="row mt-4">
    <table class="table" v-if="history.length">
        <thead>
            <th scope="col">#</th>
            <th scope="col"><?= __('Event type') ?></th>
            <th scope="col"><?= __('Description') ?></th>
            <th scope="col"><?= __('Changed by') ?></th>
            <th scope="col"><?= __('Date') ?></th>
        </thead>
        <tbody>
            <tr v-for="(record, index) in ticketHistory">
                <td>{{ index + 1 }}</td>
                <td>{{ record.event_type.name }}</td>
                <td v-html="record.event_type.template"></td>
                <td v-if="record.changeByInfo">
                    <img v-if="record.changeByInfo.photo" class="rounded-circle avatar-img-history" v-bind:alt="record.changeByInfo.title" v-bind:src="record.changeByInfo.photo" />
                    <span v-else class="border rounded-circle p-2">{{ record.changeByInfo.abr }}</span>
                    {{ record.changeByInfo.title }}
                </td>
                <td v-else><?= __('System') ?></td>
                <td>{{ record.created }}</td>
            </tr>
        </tbody>
    </table>
</div>
