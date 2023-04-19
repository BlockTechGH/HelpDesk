<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Ticket Entity
 *
 * @property int $id
 * @property int $status_id
 * @property int $category_id
 * @property string $member_id
 * @property int $action_id
 * @property int $source_type_id
 * @property string $source_id
 *
 * @property \App\Model\Entity\TicketStatus $ticket_status
 * @property \App\Model\Entity\TicketCategory $ticket_category
 */
class Ticket extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'status_id' => true,
        'category_id' => true,
        'incident_category_id' => true,
        'member_id' => true,
        'action_id' => true,
        'source_type_id' => true,
        'source_id' => true,
        'ticket_status' => true,
        'ticket_category' => true,
        'sla_notified' => true,
        'bitrix_users' => true,
    ];
}
