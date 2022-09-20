<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TicketStatusesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TicketStatusesTable Test Case
 */
class TicketStatusesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TicketStatusesTable
     */
    protected $TicketStatuses;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.TicketStatuses',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('TicketStatuses') ? [] : ['className' => TicketStatusesTable::class];
        $this->TicketStatuses = $this->getTableLocator()->get('TicketStatuses', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->TicketStatuses);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\TicketStatusesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
