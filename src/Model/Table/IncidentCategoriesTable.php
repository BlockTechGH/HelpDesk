<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * IncidentCategories Model
 *
 * @method \App\Model\Entity\IncidentCategory newEmptyEntity()
 * @method \App\Model\Entity\IncidentCategory newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\IncidentCategory[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\IncidentCategory get($primaryKey, $options = [])
 * @method \App\Model\Entity\IncidentCategory findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\IncidentCategory patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\IncidentCategory[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\IncidentCategory|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\IncidentCategory saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\IncidentCategory[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\IncidentCategory[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\IncidentCategory[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\IncidentCategory[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class IncidentCategoriesTable extends Table
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

        $this->setTable('incident_categories');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('member_id')
            ->maxLength('member_id', 255)
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('active')
            ->maxLength('active', 1)
            ->notEmptyString('active');

        return $validator;
    }

    public function getCategoriesFor(string $memberId)
    {
        $rawList = $this->find()
            ->where([
                'member_id' => $memberId,
            ])
            ->all()
            ->toList();
        $result = [];
        foreach ($rawList as $category) {
            $result[$category->id] = $category;
        }
        return $result;
    }

    public function getDefaultFor(string $memberId)
    {
        return $this->find()
            ->where([
                'member_id' => $memberId,
                'active' => true,
            ])
            ->order(['id' => 'asc'])
            ->first();
    }

    public function editCategory($id, string $name, string $memberId, $active)
    {
        $insert = [
            'member_id' => $memberId,
            'name' => $name,
            'active' => (int)$active
        ];
        if($id < 1)
        {
            $category = $this->newEntity($insert);
        } else {
            $category = $this->get($id);
            $category = $this->patchEntity($category, $insert);
        }
        $this->save($category);
        return [
            'id' => $category->id,
            'name' => $category->name,
            'active' => !!$category->active,
            'member_id' => $category->member_id,
        ];
    }
}
