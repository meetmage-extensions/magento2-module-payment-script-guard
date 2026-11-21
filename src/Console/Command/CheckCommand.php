<?php
declare(strict_types=1);

namespace MeetMage\PaymentScriptGuard\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use MeetMage\PaymentScriptGuard\Model\Integrity\Checker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento meetmage:psg:check: run the payment page script integrity check now.
 */
class CheckCommand extends Command
{
    /**
     * @var Checker
     */
    private Checker $checker;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @param Checker $checker
     * @param State $appState
     */
    public function __construct(Checker $checker, State $appState)
    {
        $this->checker = $checker;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     * Command name and description.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('meetmage:psg:check')
            ->setDescription('Check payment page scripts against their approved SHA-384 hashes');
        parent::configure();
    }

    /**
     * Run the check and print a summary.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        }
        $stats = $this->checker->checkAll();
        $output->writeln(sprintf(
            '<info>%d script file(s) checked, %d changed, %d could not be fetched.</info>',
            $stats['checked'],
            $stats['changed'],
            $stats['unreachable']
        ));
        if ($stats['changed'] > 0) {
            $output->writeln('<comment>Review the changed scripts under System > Payment Script Guard.</comment>');
        }
        return $stats['changed'] > 0 ? 2 : 0;
    }
}
