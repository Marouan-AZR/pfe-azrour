<?php

namespace App\Form;

use App\Entity\Client;
use App\Enum\InvoiceStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'companyName',
                'label' => 'Client',
                'placeholder' => 'Tous les clients',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'placeholder' => 'Tous les statuts',
                'required' => false,
                'choices' => [
                    'Brouillon' => InvoiceStatus::DRAFT->value,
                    'En attente de validation' => InvoiceStatus::PENDING_VALIDATION->value,
                    'Validée' => InvoiceStatus::VALIDATED->value,
                    'Envoyée' => InvoiceStatus::SENT->value,
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
