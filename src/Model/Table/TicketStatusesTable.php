<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Http\Session\DatabaseSession;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Exception;

/**
 * TicketStatuses Model
 *
 * @method \App\Model\Entity\TicketStatus newEmptyEntity()
 * @method \App\Model\Entity\TicketStatus newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus get($primaryKey, $options = [])
 * @method \App\Model\Entity\TicketStatus findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\TicketStatus patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketStatus saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class TicketStatusesTable extends Table
{
    public const MARK_INTERMEDIATE = 0;
    public const MARK_STARTABLE = 1;
    public const MARK_FINAL = 2;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('ticket_statuses');
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
            ->integer('mark')
            ->greaterThan('mark', -1)
            ->lessThan('mark', 3);

        return $validator;
    }

    public function getFirstStatusForMemberTickets(string $memberId, int $mark = 1)
    {
        $status = $this->find()
            ->where([
                'member_id' => $memberId,
                'active' => true,
                'mark' => $mark
            ])
            ->orderAsc('created')
            ->first();
        if (!$status) {
            $query = $this->find()
                ->where([
                    'member_id' => $memberId,
                    'active' => true,
                ]);
            if ($mark < static::MARK_FINAL) {
                $query->orderAsc('created');
            } else {
                $query->orderDesc('created');
            }
            $status = $query->first();
        }
        return $status;
    }

    public function getStatusesFor(string $memberId)
    {
        $rawList = $this->find()
            ->where([
                'member_id' => $memberId,
            ])
            ->all()
            ->toList();
        $list = [];
        foreach($rawList as $status)
        {
            $status->color = "#{$status->color}";
            $list[$status->id] = $status;
        }
        return $list;
    }

    public function editStatus($id = null, string $name, string $memberId, bool $active = true, int $mark = 0, string $color)
    {
        $insert = [
            'member_id' => $memberId,
            'name' => $name,
            'active' => (int)$active,
            'mark' => $mark >= static::MARK_INTERMEDIATE && $mark <= static::MARK_FINAL ? $mark : static::MARK_INTERMEDIATE,
            'color' => mb_ereg_replace('#', '', $color),
        ];
        if($id == null)
        {
            $status = $this->newEntity($insert);
        } else {
            $status = $this->get($id);
            $status = $this->patchEntity($status, $insert);
        }
        if(!$this->save($status) || $status->hasErrors())
        {
            $errorLines = [];
            foreach($status->getErrors() as $prop => $error)
            {
                $bugs = array_map(function ($bug) use ($prop) { return "{$prop} - {$bug};"; }, array_values($error));
                $errorLines = array_merge($errorLines, $bugs);
            }
            $errorMessage = implode("\n", $errorLines);
            throw new Exception($errorMessage);
        }

        return [
            'id' => $status->id,
            'name' => $status->name,
            'active' => !!$status->active,
            'member_id' => $status->member_id,
            'mark' => $status->mark,
            'color' => $status->color,
        ];
    }

    public function flushMarks(int $mark)
    {
        $this->updateAll([
            'mark' => static::MARK_INTERMEDIATE
        ], [
            'mark' => $mark
        ]);
    }

    public function getStatusesByMemberIdAndMarks(string $memberId, array $marks = [])
    {
        $statuses = $this->find()->where([
                'member_id' => $memberId,
                'mark IN' => $marks
            ])->toArray();
        return $statuses;
    }

    public function getEscalatedStatus($memberId)
    {
        return $this->find()->where(['member_id' => $memberId, 'mark' => 3])->first();
    }
}
