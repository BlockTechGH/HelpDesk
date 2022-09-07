<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * LangCodes Model
 *
 * @method \App\Model\Entity\LangCode newEmptyEntity()
 * @method \App\Model\Entity\LangCode newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\LangCode[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\LangCode get($primaryKey, $options = [])
 * @method \App\Model\Entity\LangCode findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\LangCode patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\LangCode[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\LangCode|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\LangCode saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\LangCode[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\LangCode[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\LangCode[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\LangCode[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class LangCodesTable extends Table
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

        $this->setTable('lang_codes');
        $this->setDisplayField('name');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('code')
            ->maxLength('code', 100)
            ->requirePresence('code', 'create')
            ->notEmptyString('code');

        return $validator;
    }

    public function getSelectableList() : array
    {
        $collection = $this
            ->find()
            ->all();
        $listToDisplay = [];
        foreach ($collection as $langRecord)
        {
            $listToDisplay[$langRecord->id] = [
                'title' => $langRecord->name,
                'code' => $langRecord->code,
                'locale' => $langRecord->name,
                'id' => $langRecord->id
            ];
        }
        return $listToDisplay;
    }

    public function getOne(int $id)
    {
        return $this->find()->where(['id' => $id])->first();
    }
}
