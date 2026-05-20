<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'Raison sociale',
            ])
            ->add('code', TextType::class, [
                'label' => 'Code client',
                'required' => false,
                'disabled' => true,
                'attr' => ['placeholder' => 'CLI-XXXXXXXX (auto-généré)'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'attr' => ['rows' => 3],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('numeroAgrement', TextType::class, [
                'label' => "N° d'agrément",
                'required' => false,
                'attr' => ['placeholder' => "Ex: 12345"],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
