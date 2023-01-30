<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TicketBindingsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TicketBindingsTable Test Case
 */
class TicketBindingsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TicketBindingsTable
     */
    protected $TicketBindings;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.TicketBindings',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('TicketBindings') ? [] : ['className' => TicketBindingsTable::class];
        $this->TicketBindings = $this->getTableLocator()->get('TicketBindings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->TicketBindings);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\TicketBindingsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
