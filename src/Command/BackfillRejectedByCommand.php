<?php

namespace App\Command;

use App\Entity\AuditLog;
use App\Entity\Operation;
use App\Enum\StockStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-rejected-by',
    description: 'Backfill validated_by/validated_at for rejected operations that predate the fix.',
)]
class BackfillRejectedByCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $operations = $this->em->getRepository(Operation::class)->findBy([
            'status'      => StockStatus::REJECTED,
            'validatedBy' => null,
        ]);

        if (empty($operations)) {
            $io->success('Aucune opération rejetée sans décideur trouvée.');
            return Command::SUCCESS;
        }

        $fixed = 0;
        foreach ($operations as $operation) {
            $log = $this->em->getRepository(AuditLog::class)->findOneBy(
                ['action' => 'operation_rejected', 'entityId' => $operation->getId()],
                ['createdAt' => 'DESC']
            );

            if ($log) {
                $operation->setValidatedBy($log->getUser());
                $operation->setValidatedAt($log->getCreatedAt());
                $fixed++;
                $io->writeln(sprintf(
                    '  ✓ %s → rejeté par %s le %s',
                    $operation->getCode(),
                    $log->getUser()->getFullName(),
                    $log->getCreatedAt()->format('d/m/Y H:i')
                ));
            } else {
                $io->warning(sprintf('Aucun log audit trouvé pour %s', $operation->getCode()));
            }
        }

        $this->em->flush();
        $io->success(sprintf('%d opération(s) mise(s) à jour.', $fixed));

        return Command::SUCCESS;
    }
}
