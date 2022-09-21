<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * HelpdeskOptions Model
 *
 * @method \App\Model\Entity\HelpdeskOption newEmptyEntity()
 * @method \App\Model\Entity\HelpdeskOption newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\HelpdeskOption[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\HelpdeskOption get($primaryKey, $options = [])
 * @method \App\Model\Entity\HelpdeskOption findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\HelpdeskOption patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\HelpdeskOption[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\HelpdeskOption|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\HelpdeskOption saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\HelpdeskOption[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\HelpdeskOption[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\HelpdeskOption[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\HelpdeskOption[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class HelpdeskOptionsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('helpdesk_options');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('member_id')
            ->maxLength('member_id', 255)
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('opt')
            ->maxLength('opt', 255)
            ->requirePresence('opt', 'create')
            ->notEmptyString('opt');

        $validator
            ->scalar('value')
            ->maxLength('value', 255)
            ->requirePresence('value', 'create')
            ->notEmptyString('value');

        return $validator;
    }

    public function getSettingsFor(string $member_id)
    {
        return $this->find()
            ->where([
                'member_id' => $member_id
            ])
            ->toList();
    }

    public function updateOptions(array $settings)
    {
        foreach ($settings as $update)
        {
            $option = $this->find()
                ->where([
                    'member_id' => $update['member_id'],
                    'opt' => $update['opt'],
                ])
                ->first();
            if(!$option)
            {
                $option = $this->newEntity($update);
            } else {
                $option = $this->patchEntity($option, $update);
            }
            if(!$option->hasErrors())
            {
                $this->save($option);
            }
        }
    }

    public function addOption(string $name, string $value, string $memberId)
    {
        $option = $this->newEntity([
            'member_id' => $memberId,
            'opt' => $name,
            'value' => $value,
            'active' => (int)(true)
        ]);
        return $this->save($option);
    }
}
