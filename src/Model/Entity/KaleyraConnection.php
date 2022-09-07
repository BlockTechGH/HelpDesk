<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * KaleyraConnection Entity
 *
 * @property int $id
 * @property string $member_id
 * @property string $phone_number
 * @property string $sid
 * @property string $api_key
 * @property int $line
 * @property string $widget_name
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 */
class KaleyraConnection extends Entity
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
        'phone_number' => true,
        'line' => true,
        'api_key' => true,
        'widget_name' => true,
        'sid' => true,
        'created' => true,
        'modified' => true,
    ];
}
