<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WhatsappMessageTemplatesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WhatsappMessageTemplatesTable Test Case
 */
class WhatsappMessageTemplatesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WhatsappMessageTemplatesTable
     */
    protected $WhatsappMessageTemplates;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.WhatsappMessageTemplates',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('WhatsappMessageTemplates') ? [] : ['className' => WhatsappMessageTemplatesTable::class];
        $this->WhatsappMessageTemplates = $this->getTableLocator()->get('WhatsappMessageTemplates', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WhatsappMessageTemplates);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WhatsappMessageTemplatesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
