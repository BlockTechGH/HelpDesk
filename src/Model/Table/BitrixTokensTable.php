<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\BitrixToken;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * BitrixTokens Model
 *
 * @method \App\Model\Entity\BitrixToken newEmptyEntity()
 * @method \App\Model\Entity\BitrixToken newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\BitrixToken[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\BitrixToken get($primaryKey, $options = [])
 * @method \App\Model\Entity\BitrixToken findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\BitrixToken patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\BitrixToken[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\BitrixToken|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\BitrixToken saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\BitrixToken[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\BitrixToken[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\BitrixToken[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\BitrixToken[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class BitrixTokensTable extends Table
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

        $this->setTable('bitrix_tokens');
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
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('auth_id')
            ->maxLength('auth_id', 255)
            ->requirePresence('auth_id', 'create')
            ->notEmptyString('auth_id');

        $validator
            ->scalar('refresh_id')
            ->maxLength('refresh_id', 255)
            ->requirePresence('refresh_id', 'create')
            ->notEmptyString('refresh_id');

        $validator
            ->integer('auth_expires')
            ->requirePresence('auth_expires', 'create')
            ->notEmptyString('auth_expires');

        return $validator;
    }

    public function writeAppTokens($memberId, $domain, $authToken, $refreshToken, $expires): BitrixToken
    {
        $token = $this->findOrCreate([
                'member_id' => $memberId,
            ],
            function ($token) use ($memberId, $expires, $authToken, $refreshToken, $domain) {
                $token->member_id = $memberId;
                $token->auth_expires = $expires;
                $token->auth_id = $authToken;
                $token->refresh_id = $refreshToken;
                $token->domain = $domain;
            });
        $token->auth_expires = $expires;
        $token->auth_id = $authToken;
        $token->refresh_id = $refreshToken;
        $token->domain = $domain;
        $this->save($token);
        return $token;
    }

    public function getTokenObjectByMemberId(string $memberId)
    {
        return $this
            ->find()
            ->where([
                'member_id' => $memberId
            ])
            ->first();
    }
}
