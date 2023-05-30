<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TicketHistory Entity
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property int $event_type_id
 * @property string|null $old_value
 * @property string|null $new_value
 * @property \Cake\I18n\FrozenTime $created
 *
 * @property \App\Model\Entity\Ticket $ticket
 * @property \App\Model\Entity\EventType $event_type
 */
class TicketHistory extends Entity
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
        'ticket_id' => true,
        'user_id' => true,
        'event_type_id' => true,
        'old_value' => true,
        'new_value' => true,
        'created' => true,
        'ticket' => true,
        'event_type' => true,
    ];
}
