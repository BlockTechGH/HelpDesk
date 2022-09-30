<?php
$this->start('title');
    echo __('New reply in ticket {0}', $subject);
$this->end();
?>

<?= $this->Html->script('fit_window'); ?>
<div id="feedback_form" class="row mt-3">
    <form 
        method="POST" 
        action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
        enctype="multipart/form-data"
    >
        <div class="form-group">
            <h1><?=__('New reply in ticket {0}', $subject);?></h1>
        </div>
        <div class="forn-group">{{ answer.from }}</div>
        <div class="form-group pt-1">
            <label for="message">{{ i18n.Reply }}</label>
            <textarea id="message" name="message" v-model="answer.message"></textarea>
        </div>
        <div class="form-group pt-1">
            <label for="attachment">{{ i18n.Attachment }}</label>
            <input type="file" name="attachment" id="attachment" @change="upload($event)">
        </div>
        <div class="btn-group pt-1">
            <button type="button" name="answer" class="btn btn-primary" @click="send">
                {{ i18n.Send }}
            </button>
        </div>
    </form>
</div>

<script>
    window.data = {
        ajax: "<?=$ajax;?>",
        required: <?=json_encode($required)?>,
        answer: <?=json_encode($answer)?>,
        subject: '<?=$subject;?>',
        i18n: <?=json_encode([
            'Response' => __('Response'),
            'Reply' => __('Your answer'),
            'EnterReplicaText' => __('Enter your answer here'),
            'Send' => __('Send'),
            'Attachment' => __('Attache file'),
        ]);?>,
    };
</script>

<script>
    new Vue({
        'el': '#feedback_form',
        'data': window.data,
        'methods': {
            send: function() {
                const parameters = Object.assign(
                    {
                        answer: this.answer,
                        ticket: this.ticket,
                    },
                    this.required
                );

                const headers = { 'Content-Type': 'application/json' };
                fetch(this.ajax, {
                    method: "POST",
                    headers,
                    body: JSON.stringify(parameters),
                }).then(async result => {
                    const all = await result.json();
                    this.messages = all;
                });
            },
            upload: function() {
            //    this.answer.attachment = this.$refs.attachment.files[0];
            }
        }
    });
</script>