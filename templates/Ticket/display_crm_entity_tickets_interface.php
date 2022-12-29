<?php $this->start('css');?>
    <?=$this->Html->css('home');?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.1/jquery.bootgrid.min.css" integrity="sha512-WBBdLBZSQGm9JN1Yut45Y9ijfFANbcOX3G+/A5+oO8W2ZWASp3NkPrG8mgr8QvGviyLoAz8y09l7SJ1dt0as7g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha512-SfTiTlX6kk+qitfevl/7LibUOeJWlt9rbyDn92a1DqWOw9vWG2MFoays0sgObmWazO5BQPiFucnnEAjpAB+/Sw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<?php $this->end();?>

<?php $this->start('script');?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.0/jquery.bootgrid.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.1/jquery.bootgrid.fa.min.js" integrity="sha512-9n0UG6HszJFRxzkSCxUItSZeu48ecVvY95pRVu0GDhRspSavKvKcm04U96VYeNLPSb2lCDOZ5wXCDbowg1gHhg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<?php $this->end();?>

<div>
    <table id="ticketsGrid" class="table table-condensed table-hover table-striped">
        <thead>
            <th data-column-id="id"><?=__('ID');?></th>
            <th data-column-id="name"><?=__('Name');?></th>
            <th data-column-id="responsible" ><?=__('Responsible person');?></th>
            <th data-column-id="status_id" data-formatter="status"><?=__('Status');?></th>
            <th data-column-id="client"><?=__('Client');?></th>
            <th data-column-id="created" data-order="desc"><?=__('Created');?></th>
        </thead>
    </table>
</div>

<script>
// Grid
let grid = $('#ticketsGrid').bootgrid({
        rowCount: 50,
        formatters: {
            'person': (column, row) => row[column.id]?.title,
            'status': (column, row) => json_encode($statuses)[row.status_id].name,
        },
        templates: {
            header: "<div id=\"{{ctx.id}}\" class=\"{{css.header}}\"><div class=\"row\"><div class=\"actionBar\"><p class=\"{{css.actions}}\"></p></div></div></div>",
        },
        css: {
            paginationButton: "btn border",
            selected: "active text-primary"
        },
        ajax: true,
        url: "<?= $this->Url->build(['_name' => 'crm_entity_tickets_interface']); ?>",
        post: function (request)
        {
            var auth = BX24.getAuth();
            if (typeof(request) == "undefined")
            {
                request = {};
            }
            request['memberId'] = auth.member_id;
            request['auth'] = auth;
            return request;
        }
    });
</script>