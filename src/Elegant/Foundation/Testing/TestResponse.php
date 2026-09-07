<?php

namespace Elegant\Foundation\Testing;

class TestResponse
{
    /**
     * @var object
     */
    protected $response;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var string
     */
    protected $view;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $headers;

    public function __construct(object $response)
    {
        $this->response = $response;
        $this->status = $response->status ?? 200;
        $this->view = $response->view ?? '';
        $this->data = $response->data ?? [];
        $this->headers = $response->headers ?? [];
    }

    public function assertStatus(int $status): self
    {
        \PHPUnit\Framework\Assert::assertEquals(
            $status,
            $this->status,
            "Expected status code {$status} but received {$this->status}"
        );

        return $this;
    }

    public function assertViewIs(string $view): self
    {
        \PHPUnit\Framework\Assert::assertEquals(
            $view,
            $this->view,
            "Expected view '{$view}' but got '{$this->view}'"
        );

        return $this;
    }

    public function assertSee(string $text): self
    {
        \PHPUnit\Framework\Assert::assertStringContainsString(
            $text,
            $this->getContent(),
            "Expected to see '{$text}' in response"
        );

        return $this;
    }

    public function assertDontSee(string $text): self
    {
        \PHPUnit\Framework\Assert::assertStringNotContainsString(
            $text,
            $this->getContent(),
            "Expected not to see '{$text}' in response"
        );

        return $this;
    }

    public function assertRedirect(?string $uri = null): self
    {
        \PHPUnit\Framework\Assert::assertTrue(
            $this->isRedirection(),
            'Response status code [' . $this->status . '] is not a redirect status code.'
        );

        if ($uri !== null) {
            \PHPUnit\Framework\Assert::assertEquals(
                $uri,
                $this->headers['Location'] ?? '',
                "Expected redirect to '{$uri}'"
            );
        }

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function getContent(): string
    {
        if (isset($this->response->content) && is_string($this->response->content)) {
            return $this->response->content;
        }

        $content = '<html><body>';
        $content .= '<title>' . htmlspecialchars($this->view) . '</title>';

        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                $content .= '<div class="' . htmlspecialchars((string) $key) . '">' . htmlspecialchars($value) . '</div>';
            }
        }

        $content .= '</body></html>';

        return $content;
    }

    protected function isRedirection(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }
}
