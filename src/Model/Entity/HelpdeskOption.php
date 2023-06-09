<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * HelpdeskOption Entity
 *
 * @property int $id
 * @property string $member_id
 * @property string $opt
 * @property string $value
 */
class HelpdeskOption extends Entity
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
        'member_id' => true,
        'opt' => true,
        'value' => true,
    ];
}
