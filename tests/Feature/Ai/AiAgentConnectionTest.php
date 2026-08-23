<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\VideoSearchAgent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\AgentResponse;
use ReflectionClass;
use Tests\TestCase;

/**
 * Verifies every agent in App\Ai\Agents is correctly wired into the laravel/ai
 * package (contract, provider/model attributes, a buildable schema) and that
 * the app can actually round-trip a prompt through the package's fake
 * gateway - without ever making a real network call to the AI provider.
 */
class AiAgentConnectionTest extends TestCase
{
    /**
     * @return array<int, class-string<Agent>>
     */
    private function agentClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Ai/Agents/*.php')) as $file) {
            $classes[] = 'App\\Ai\\Agents\\'.basename($file, '.php');
        }

        return $classes;
    }

    public function test_every_agent_implements_the_agent_contract_with_a_provider_and_model(): void
    {
        $classes = $this->agentClasses();
        $this->assertNotEmpty($classes, 'Expected to find agent classes under app/Ai/Agents.');

        foreach ($classes as $class) {
            $this->assertTrue(is_a($class, Agent::class, true), "$class must implement the Agent contract.");

            $reflection = new ReflectionClass($class);

            $providerAttribute = $reflection->getAttributes(ProviderAttribute::class);
            $this->assertNotEmpty($providerAttribute, "$class must declare a #[Provider] attribute.");

            $modelAttribute = $reflection->getAttributes(ModelAttribute::class);
            $this->assertNotEmpty($modelAttribute, "$class must declare a #[Model] attribute.");
            $this->assertNotSame('', $modelAttribute[0]->newInstance()->value, "$class's #[Model] value must not be empty.");

            $agent = $reflection->newInstanceWithoutConstructor();
            $this->assertNotSame('', trim((string) $agent->instructions()), "$class must provide non-empty instructions.");
        }
    }

    public function test_every_structured_agent_builds_a_valid_json_schema(): void
    {
        $structuredAgents = array_filter(
            $this->agentClasses(),
            fn (string $class) => is_a($class, HasStructuredOutput::class, true)
        );

        $this->assertNotEmpty($structuredAgents, 'Expected at least one structured-output agent.');

        foreach ($structuredAgents as $class) {
            $agent = (new ReflectionClass($class))->newInstanceWithoutConstructor();

            $schema = $agent->schema(new JsonSchemaTypeFactory);

            $this->assertNotEmpty($schema, "$class's schema() must define at least one field.");

            foreach ($schema as $field => $type) {
                $this->assertInstanceOf(Type::class, $type, "$class's schema field \"$field\" must be a JSON schema Type.");
            }
        }
    }

    public function test_an_agent_can_be_faked_and_prompted_without_a_real_network_call(): void
    {
        VideoSearchAgent::fake();

        $response = (new VideoSearchAgent)->prompt('Does the agent plumbing connect end to end?');

        $this->assertInstanceOf(AgentResponse::class, $response);
        $this->assertArrayHasKey('matches', $response->toArray());

        VideoSearchAgent::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'Does the agent plumbing connect end to end?'
        );
    }
}
