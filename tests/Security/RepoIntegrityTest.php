<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class RepoIntegrityTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/repo-integrity-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0777, true);
        copy(\dirname(__DIR__, 2).'/bin/check-repo-integrity.php', $this->directory.'/bin/check-repo-integrity.php');
        $this->execute(['git', 'init', '--quiet']);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    #[DataProvider('files')]
    public function testRejectsInjectedFilesInWorkingTreeAndIndex(string $path, string $content, bool $safe): void
    {
        new Filesystem()->mkdir(\dirname($this->directory.'/'.$path));
        file_put_contents($this->directory.'/'.$path, $content);
        $this->execute(['git', 'add', $path]);
        foreach ([[], ['--staged']] as $arguments) {
            $result = $this->execute(['php', 'bin/check-repo-integrity.php', ...$arguments]);
            self::assertSame($safe ? 0 : 1, $result->getExitCode(), $result->getOutput().$result->getErrorOutput());
        }
        if (!$safe) {
            // Une copie de travail nettoyée ne doit pas masquer l'index infecté.
            unlink($this->directory.'/'.$path);
            self::assertSame(1, $this->execute(['php', 'bin/check-repo-integrity.php', '--staged'])->getExitCode());
            $this->execute(['git', '-c', 'user.name=Integrity Test', '-c', 'user.email=integrity@example.test', 'commit', '--quiet', '-m', 'fixture']);
            self::assertSame(1, $this->execute(['php', 'bin/check-repo-integrity.php', '--ref=HEAD'])->getExitCode());
        }
    }

    public static function files(): iterable
    {
        yield 'normal browser controller' => ['assets/controllers/example.js', 'export default class {}', true];
        yield 'genuine font' => ['assets/fonts/example.woff2', 'wOF2'.str_repeat("\0", 20), true];
        yield 'fake font' => ['assets/fonts/example.woff2', 'console.log("payload")', false];
        yield 'Node loader' => ['assets/controllers/example.js', "import { createRequire } from 'module';", false];
        yield 'automatic editor task' => ['.vscode/tasks.json', '{"runOptions":{"runOn":"folderOpen"}}', false];
        yield 'hidden code' => ['assets/example.js', 'export {};'.str_repeat(' ', 150).'payload()', false];
        yield 'payload extension' => ['assets/example.orc', 'payload', false];
        yield 'decoy directory' => ['assets/lib/leaflet/images/public/fonts/README.md', 'decoy', false];
    }

    private function execute(array $command): Process
    {
        $process = new Process($command, $this->directory);
        $process->run();

        return $process;
    }
}
