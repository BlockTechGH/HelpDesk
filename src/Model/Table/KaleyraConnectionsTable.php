<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\KaleyraConnection;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * KaleyraConnections Model
 *
 * @method \App\Model\Entity\KaleyraConnection newEmptyEntity()
 * @method \App\Model\Entity\KaleyraConnection newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\KaleyraConnection[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\KaleyraConnection get($primaryKey, $options = [])
 * @method \App\Model\Entity\KaleyraConnection findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\KaleyraConnection patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\KaleyraConnection[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\KaleyraConnection|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\KaleyraConnection saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\KaleyraConnection[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\KaleyraConnection[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\KaleyraConnection[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\KaleyraConnection[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class KaleyraConnectionsTable extends Table
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

        $this->setTable('kaleyra_connections');
        $this->setDisplayField('id');
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
            ->scalar('member_id')
            ->maxLength('member_id', 255)
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('phone_number')
            ->maxLength('phone_number', 255)
            ->requirePresence('phone_number', 'create')
            ->notEmptyString('phone_number')
            ->add('phone_number', 'phoneFormat', [
                'rule' => function ($value) {
                    return (static::clearPhoneNumber($value) === $value) ? true
                        :  __('Phone number must contains digits only.');
                }
            ]);

        $validator
            ->scalar('widget_name')
            ->maxLength('widget_name', 255)
            ->requirePresence('widget_name', 'create')
            ->notEmptyString('widget_name');

        $validator
            ->scalar('sid')
            ->maxLength('sid', 255)
            ->requirePresence('sid', 'create')
            ->notEmptyString('sid');

        $validator
            ->integer('line')
            ->maxLength('line', 4)
            ->requirePresence('line', 'create')
            ->greaterThan('line', 0);

        $validator
            ->scalar('api_key')
            ->maxLength('api_key', 255)
            ->requirePresence('api_key', 'create')
            ->notEmptyString('api_key');

        return $validator;
    }

    public function addConnection(string $memberId, string $apiKey, string $phoneNumber, string $sid, int $line, string $widgetName)
    {
        $phoneNumber = static::clearPhoneNumber($phoneNumber);
        $update = [
            'phone_number' => $phoneNumber,
            'widget_name' => $widgetName,
            'sid' => $sid,
            'api_key' => $apiKey,
            'line' => $line,
            'member_id' => $memberId
        ];
        $record = $this
            ->find()
            ->where([
                'member_id' => $memberId,
                'line' => $line
            ])
            ->first();
        if (!$record)
        {
            $record = $this->newEntity($update);
        } else {
            $record = $this->patchEntity($record, $update);
        }

        if (!$record->hasErrors()) {
            $this->save($record);
        }
        return $record;
    }

    public function getRecordDescription(string $memberId, int $line): array
    {
        $connection = $this
            ->find()
            ->where([
                'line' => $line,
                'member_id' => $memberId,
            ])
            ->first();
        return [
            'phoneNumber' => $connection ? $connection->phone_number : "",
            'apiKey' => $connection ? $connection->api_key : "",
            'sid' => $connection ? $connection->sid : "",
            'widgetName' => $connection ? $connection->widget_name : ""
        ];
    }

    public function removeConnectionByLine(int $line)
    {
        $entity = $this
            ->find()
            ->where(['line' => $line])
            ->first();
        if (!$entity)
        {
            return;
        }
        $this->delete($entity);
    }

    public function getConnectionByPhoneNumber(string $phoneNumber)
    {
        return $this
            ->find()
            ->where([
                'phone_number' => static::clearPhoneNumber($phoneNumber)
            ])
            ->first();
    }

    public function getConnectionByLine(int $line)
    {
        return $this
            ->find()
            ->where([
                'line' => $line
            ])
            ->first();
    }

    public function getPhoneNumbers()
    {
        $connections = $this
            ->find()
            ->all();
        $phones = [];
        /** @var KaleyraConnection $connection */
        foreach ($connections as $connection) {
            $phones[$connection->phone_number] = [
                'title' => $connection->widget_name,
                'memberId' => $connection->member_id,
                'apiKey' => $connection->api_key,
                'line' => $connection->line,
                'sid' => $connection->sid,
            ];
        }
        return $phones;
    }

    public static function clearPhoneNumber(string $customPhoneNumber): string
    {
        return mb_ereg_replace('\D', '', $customPhoneNumber);
    }
}
