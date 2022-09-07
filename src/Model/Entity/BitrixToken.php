<?php
declare(strict_types=1);

namespace App\Model\Entity;

use App\Model\Table\BitrixTokensTable;
use Cake\ORM\Entity;
use Cake\ORM\Table;

/**
 * BitrixToken Entity
 *
 * @property int $id
 * @property string $member_id
 * @property string $auth_id
 * @property string $refresh_id
 * @property int $auth_expires
 * @property string $domain
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 */
class BitrixToken extends Entity
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
        'auth_id' => true,
        'refresh_id' => true,
        'auth_expires' => true,
        'domain' => true,
        'created' => true,
        'modified' => true,
    ];
}
