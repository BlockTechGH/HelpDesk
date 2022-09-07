<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\KaleyraConnectionsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\KaleyraConnectionsTable Test Case
 */
class KaleyraConnectionsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\KaleyraConnectionsTable
     */
    protected $KaleyraConnections;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.KaleyraConnections',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('KaleyraConnections') ? [] : ['className' => KaleyraConnectionsTable::class];
        $this->KaleyraConnections = $this->getTableLocator()->get('KaleyraConnections', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->KaleyraConnections);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\KaleyraConnectionsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
