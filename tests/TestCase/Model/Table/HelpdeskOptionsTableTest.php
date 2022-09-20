<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\HelpdeskOptionsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\HelpdeskOptionsTable Test Case
 */
class HelpdeskOptionsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\HelpdeskOptionsTable
     */
    protected $HelpdeskOptions;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.HelpdeskOptions',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('HelpdeskOptions') ? [] : ['className' => HelpdeskOptionsTable::class];
        $this->HelpdeskOptions = $this->getTableLocator()->get('HelpdeskOptions', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->HelpdeskOptions);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\HelpdeskOptionsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
