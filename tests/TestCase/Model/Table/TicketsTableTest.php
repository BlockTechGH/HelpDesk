<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TicketsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TicketsTable Test Case
 */
class TicketsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TicketsTable
     */
    protected $Tickets;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Tickets',
        'app.TicketStatuses',
        'app.TicketCategories',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Tickets') ? [] : ['className' => TicketsTable::class];
        $this->Tickets = $this->getTableLocator()->get('Tickets', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Tickets);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\TicketsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\TicketsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
