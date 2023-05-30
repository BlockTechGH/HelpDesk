<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TicketHistoryTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TicketHistoryTable Test Case
 */
class TicketHistoryTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TicketHistoryTable
     */
    protected $TicketHistory;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.TicketHistory',
        'app.Tickets',
        'app.EventTypes',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('TicketHistory') ? [] : ['className' => TicketHistoryTable::class];
        $this->TicketHistory = $this->getTableLocator()->get('TicketHistory', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->TicketHistory);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\TicketHistoryTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\TicketHistoryTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
