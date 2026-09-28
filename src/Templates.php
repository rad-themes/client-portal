<?php

namespace Komalnakrani\ClientPortal;

class Templates
{
    /**
     * Get default predefined portal templates.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'website-redesign' => [
                'slug' => 'website-redesign',
                'title' => 'Website Design & Development',
                'description' => 'Complete workflow for client website redesign projects, including brief, Figma wireframes, staging, and launch sign-off.',
                'project_status' => 'In Progress',
                'welcome' => 'Welcome to your Website Redesign portal! Track project phases, review wireframes, access staging previews, and share feedback here.',
                'phases' => [
                    [
                        'id' => 'phase-discovery',
                        'title' => 'Phase 1: Discovery & Strategy',
                        'modules' => [
                            [
                                'id' => 'mod-brief',
                                'type' => 'content',
                                'title' => 'Project Brief & Scope',
                                'description' => 'Detailed goals, target audience, technical requirements, and project timeline.',
                                'status' => 'active',
                                'body' => [
                                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Welcome to our project kickoff! Please review our goals and scope outlined below.']]],
                                ],
                            ],
                            [
                                'id' => 'mod-questionnaire',
                                'type' => 'link',
                                'title' => 'Client Onboarding Questionnaire',
                                'description' => 'Help us gather brand assets, copy requirements, and design preferences.',
                                'status' => 'active',
                                'url' => 'https://typeform.com',
                                'button_label' => 'Fill Questionnaire',
                            ],
                        ],
                    ],
                    [
                        'id' => 'phase-design',
                        'title' => 'Phase 2: Wireframes & Design Concepts',
                        'modules' => [
                            [
                                'id' => 'mod-figma',
                                'type' => 'embed',
                                'title' => 'Figma Prototype & Visual Design',
                                'description' => 'Interactive high-fidelity wireframes and responsive UI design concepts.',
                                'status' => 'active',
                                'embed_url' => 'https://www.figma.com',
                                'embed_code' => '<iframe style="border: 1px solid rgba(0, 0, 0, 0.1);" width="800" height="450" src="https://www.figma.com/embed?embed_host=share&url=https://www.figma.com" allowfullscreen></iframe>',
                            ],
                            [
                                'id' => 'mod-loom-walkthrough',
                                'type' => 'embed',
                                'title' => 'Video Walkthrough of Design',
                                'description' => '5-minute video presentation explaining the key UX decisions.',
                                'status' => 'active',
                                'embed_url' => 'https://www.loom.com',
                            ],
                            [
                                'id' => 'mod-design-approval',
                                'type' => 'action',
                                'title' => 'Design Sign-Off & Approval',
                                'description' => 'Please approve the design concepts before we transition into front-end development.',
                                'status' => 'pending_approval',
                                'action_label' => 'Approve Design',
                                'action_required' => true,
                            ],
                        ],
                    ],
                    [
                        'id' => 'phase-development',
                        'title' => 'Phase 3: Development & Staging',
                        'modules' => [
                            [
                                'id' => 'mod-staging-link',
                                'type' => 'link',
                                'title' => 'Staging Environment Preview',
                                'description' => 'Test site build running on our secure staging server.',
                                'status' => 'active',
                                'url' => 'https://staging.example.com',
                                'button_label' => 'View Staging Site',
                            ],
                            [
                                'id' => 'mod-assets-download',
                                'type' => 'file',
                                'title' => 'Website Content & Media Assets',
                                'description' => 'Downloadable media package and final website exports.',
                                'status' => 'active',
                                'files' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'brand-identity' => [
                'slug' => 'brand-identity',
                'title' => 'Brand Identity & Logo Design',
                'description' => 'Brand identity package including logo concepts, typography, color palette, and brand guidelines document.',
                'project_status' => 'Discovery',
                'welcome' => 'Welcome to your Brand Identity portal! All visual assets, logo variations, and style guides will be organized here.',
                'phases' => [
                    [
                        'id' => 'phase-brand-discovery',
                        'title' => 'Phase 1: Brand Strategy & Moodboard',
                        'modules' => [
                            [
                                'id' => 'mod-brand-brief',
                                'type' => 'content',
                                'title' => 'Brand Strategy Overview',
                                'description' => 'Core values, target demographic, and brand positioning.',
                                'status' => 'active',
                                'body' => [
                                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'This document outlines your brand core attributes and creative direction.']]],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'phase-logo-concepts',
                        'title' => 'Phase 2: Logo Concepts & Presentation',
                        'modules' => [
                            [
                                'id' => 'mod-logo-presentation',
                                'type' => 'link',
                                'title' => 'Logo Concepts Deck (PDF)',
                                'description' => 'Review 3 distinct logo directions with real-world mockups.',
                                'status' => 'active',
                                'url' => 'https://example.com/logo-deck.pdf',
                                'button_label' => 'View Deck',
                            ],
                            [
                                'id' => 'mod-logo-signoff',
                                'type' => 'action',
                                'title' => 'Final Logo Selection Sign-Off',
                                'description' => 'Confirm your selected logo direction for final vector export.',
                                'status' => 'pending_approval',
                                'action_label' => 'Confirm Logo Choice',
                                'action_required' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
