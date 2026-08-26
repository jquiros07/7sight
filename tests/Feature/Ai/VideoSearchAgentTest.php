<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\VideoSearchAgent;
use Laravel\Ai\Enums\Lab;
use Tests\TestCase;

class VideoSearchAgentTest extends TestCase
{
    /**
     * gemini-3.6-flash rejects a thinkingBudget of 0 (a full disable) with a
     * 400 - 1 is the lowest value it accepts, and capping it there is what
     * cut real response times from a 3s-68s spread down to ~2s-13s.
     */
    public function test_it_caps_gemini_thinking_budget_to_its_minimum(): void
    {
        $options = (new VideoSearchAgent)->providerOptions(Lab::Gemini);

        $this->assertSame(['thinkingConfig' => ['thinkingBudget' => 1]], $options);
    }

    public function test_it_returns_no_provider_options_for_a_non_gemini_provider(): void
    {
        $options = (new VideoSearchAgent)->providerOptions(Lab::OpenAI);

        $this->assertSame([], $options);
    }
}
