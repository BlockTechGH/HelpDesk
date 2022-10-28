<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TicketStatus Entity
 *
 * @property int $id
 * @property string $name
 * @property string $member_id
 * @property bool $active
 * @property int $mark
 */
class TicketStatus extends Entity
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
        'name' => true,
        'member_id' => true,
        'active' => true,
        'mark' => true,
        'color' => true,
    ];
}
