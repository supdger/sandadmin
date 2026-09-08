<?php

declare(strict_types=1);

namespace plugin\sandpackage\app\command;

use plugin\sandpackage\app\logic\InstallLogic;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use plugin\sandpackage\app\logic\FailedUpgradeRecoveryAudit;

/** Controlled CLI counterpart of the failed-upgrade recovery endpoints. */
class Recover extends Command
{
    protected static $defaultName = 'sandpackage:recover';
    protected static $defaultDescription = '检查或执行已确认的 SandPackage 失败升级恢复操作';

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'inspect|restore|verify|prepare|replace|retry')
            ->addArgument('app', InputArgument::REQUIRED, '插件标识')
            ->addOption('replacement-id', null, InputOption::VALUE_REQUIRED, '已预检替换候选标识')
            ->addOption('archive', null, InputOption::VALUE_REQUIRED, 'prepare 的本地 ZIP 文件')
            ->addOption('confirmation', null, InputOption::VALUE_REQUIRED, '写操作的精确确认文本')
            ->addOption('actor-label', null, InputOption::VALUE_REQUIRED, '仅附加到本次 CLI 输出的操作者标签');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = (string) $input->getArgument('action');
        $app = (string) $input->getArgument('app');
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
        $account = function_exists('posix_getpwuid') ? posix_getpwuid($uid) : false;
        $actor = (int) $uid;
        if ($actor < 0) {
            $io->error('无法确定当前 OS 身份。');
            return Command::FAILURE;
        }
        $logic = new InstallLogic($app);
        try {
            $result = match ($action) {
                'inspect' => $logic->inspectFailedUpgradeRecovery((int) $actor),
                'restore' => $logic->restoreRuntimeFromBackup((string) $input->getOption('confirmation'), FailedUpgradeRecoveryAudit::cliActor()),
                'verify' => $logic->verifyPreparedFailedUpgradeReplacement((string) $input->getOption('replacement-id'), FailedUpgradeRecoveryAudit::cliActor()),
                'prepare' => $logic->prepareFailedUpgradeReplacement($this->archive((string) $input->getOption('archive')), FailedUpgradeRecoveryAudit::cliActor()),
                'replace' => $logic->replaceFailedUpgradeCandidate((string) $input->getOption('replacement-id'), (string) $input->getOption('confirmation'), FailedUpgradeRecoveryAudit::cliActor()),
                'retry' => $logic->retryFailedUpgrade((string) $input->getOption('confirmation'), FailedUpgradeRecoveryAudit::cliActor()),
                default => throw new \InvalidArgumentException('未知恢复操作。'),
            };
            $result['cli_actor'] = ['uid' => $uid, 'username' => is_array($account) ? ($account['name'] ?? null) : null, 'label' => $input->getOption('actor-label')];
            $io->writeln(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            $io->error($error->getMessage());
            return Command::FAILURE;
        }
    }

    private function archive(string $path): object
    {
        if ($path === '' || !is_file($path) || is_link($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'zip') {
            throw new \InvalidArgumentException('prepare 必须提供一个现存的普通 ZIP 文件。');
        }
        return new class($path) { public function __construct(private string $path) {} public function getPathname(): string { return $this->path; } };
    }
}
