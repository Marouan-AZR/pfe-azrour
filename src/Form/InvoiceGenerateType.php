<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceGenerateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'companyName',
                'label' => 'Client',
                'placeholder' => 'Sélectionner un client',
                'query_builder' => fn($repo) => $repo->createQueryBuilder('c')
                    ->where('c.isActive = true')
                    ->orderBy('c.companyName', 'ASC'),
            ])
            ->add('periodStart', DateType::class, [
                'label' => 'Début de période',
                'widget' => 'single_text',
            ])
            ->add('periodEnd', DateType::class, [
                'label' => 'Fin de période',
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
