<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WhatsappMessageTemplate;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * WhatsappMessageTemplates Model
 *
 * @method \App\Model\Entity\WhatsappMessageTemplate newEmptyEntity()
 * @method \App\Model\Entity\WhatsappMessageTemplate newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate get($primaryKey, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\WhatsappMessageTemplate[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class WhatsappMessageTemplatesTable extends Table
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

        $this->setTable('whatsapp_message_templates');
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
            ->scalar('placeholders')
            ->maxLength('placeholders', 255)
            ->requirePresence('placeholders', 'create');

        return $validator;
    }

    public function getOne(string $name, int $lang)
    {
        return $this->find()->where(['name' => $name, 'id_lang' => $lang])->first();
    }

    public function getSelectableList()
    {
        $listForDisplay = [];
        $all = $this->find()->all();
        /** @var WhatsappMessageTemplate $template */
        foreach ($all as $template) {
            $listForDisplay[$template->id] = [
                'id' => $template->id,
                'title' => $template->name,
                'id_lang' => $template->id_lang,
                'placeholders' => $template->placeholders,
                'header' => $template->header,
                'selected' => false,
            ];
        }
        return $listForDisplay;
    }

    public function extendByLanguages(array &$templatesSelectableList, array &$languagesSelectableList)
    {
        foreach ($templatesSelectableList as $i => $template)
        {
            $templatesSelectableList[$i]['lang_code'] = $languagesSelectableList[$template['id_lang']]['title'];
        }
        return $templatesSelectableList;
    }

    public function store(array $data)
    {
        $entity = $this->find()->where(['id' => $data['id']])->first();
        if (!!$entity) {
            $this->patchEntity($entity, $data);
        } else {
            $entity = $this->newEntity($data);
        }
        $this->save($entity);
        if ($entity->hasErrors()) {
            throw new \Exception(print_r($entity->getErrors(), true));
        }
        return $entity->id;
    }

    public function remove($id, string $name = null)
    {
        $searchCriteria = [];
        if (!!$id) {
            $searchCriteria['id'] = $id;
        }
        if (!!$name) {
            $searchCriteria['name'] = $name;
        }
        $entity = $this->find()->where($searchCriteria)->first();
        if ($entity) {
            $this->delete($entity);
        } elseif (!!$name) {
            $entity = $this->find()->where(['name' => $name])->first();
            if ($entity) {
                $this->delete($entity);
            }
        }
    }
}
