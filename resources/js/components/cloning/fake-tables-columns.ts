import type { CloningTable } from '@/components/cloning/cloning';


export const FAKE_TABLES_COLUMNS: CloningTable[] = [
    {
        "name": "author_types",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [
                    {
                        "table": "contributors",
                        "column": "author_type_id",
                        "selfReferential": false
                    }
                ],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "name",
                "strategy": "fake",
                "fakerMethod": "name",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité personnelle",
                    "name": "Nom complet",
                    "sensitivity": "high"
                }
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "label",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "cache",
        "columns": [
            {
                "name": "key",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "value",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "expiration",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "cache_locks",
        "columns": [
            {
                "name": "key",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "owner",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "expiration",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "comments",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "content",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "user_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "full_name",
                "strategy": "fake",
                "fakerMethod": "name",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité personnelle",
                    "name": "Nom complet",
                    "sensitivity": "high"
                }
            },
            {
                "name": "email",
                "strategy": "fake",
                "fakerMethod": "safeEmail",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Coordonnées de contact",
                    "name": "Adresse email",
                    "sensitivity": "high"
                }
            },
            {
                "name": "commentable_id",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "commentable_type",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "hash",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "contributorables",
        "columns": [
            {
                "name": "contributor_id",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "contributorable_id",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "contributorable_type",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "contributors",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "full_name",
                "strategy": "fake",
                "fakerMethod": "name",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité personnelle",
                    "name": "Nom complet",
                    "sensitivity": "high"
                }
            },
            {
                "name": "author_type_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "email",
                "strategy": "fake",
                "fakerMethod": "safeEmail",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Coordonnées de contact",
                    "name": "Adresse email",
                    "sensitivity": "high"
                }
            },
            {
                "name": "role",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            },
            {
                "name": "image",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "failed_jobs",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "uuid",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "connection",
                "strategy": "keep",
                "type": "text",
                "detectedMatcher": null
            },
            {
                "name": "queue",
                "strategy": "keep",
                "type": "text",
                "detectedMatcher": null
            },
            {
                "name": "payload",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "exception",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "failed_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "job_batches",
        "columns": [
            {
                "name": "id",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "name",
                "strategy": "fake",
                "fakerMethod": "name",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité personnelle",
                    "name": "Nom complet",
                    "sensitivity": "high"
                }
            },
            {
                "name": "total_jobs",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "pending_jobs",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "failed_jobs",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "failed_job_ids",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "options",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "cancelled_at",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "finished_at",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "jobs",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "queue",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "payload",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "attempts",
                "strategy": "keep",
                "type": "tinyint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "reserved_at",
                "strategy": "keep",
                "type": "int unsigned",
                "detectedMatcher": null
            },
            {
                "name": "available_at",
                "strategy": "keep",
                "type": "int unsigned",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "int unsigned",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "magazines",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "title",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "description",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "image",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "pdf_url",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "number",
                "strategy": "keep",
                "type": "bigint",
                "detectedMatcher": null
            },
            {
                "name": "summary",
                "strategy": "keep",
                "type": "json",
                "detectedMatcher": null
            },
            {
                "name": "extract",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "migrations",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "int unsigned",
                "detectedMatcher": null
            },
            {
                "name": "migration",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "batch",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "news_letters",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "email",
                "strategy": "fake",
                "fakerMethod": "safeEmail",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Coordonnées de contact",
                    "name": "Adresse email",
                    "sensitivity": "high"
                }
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "offers",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "title",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "description",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "place",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "organization",
                "strategy": "fake",
                "fakerMethod": "company",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Nom de l'entreprise",
                    "sensitivity": "low"
                }
            },
            {
                "name": "expiration_date",
                "strategy": "keep",
                "type": "date",
                "detectedMatcher": {
                    "groupLabel": "Données financières",
                    "name": "Date d'expiration de la carte",
                    "sensitivity": "critical"
                }
            },
            {
                "name": "organization_url",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "images",
                "strategy": "keep",
                "type": "json",
                "detectedMatcher": null
            },
            {
                "name": "image_caption",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "type",
                "strategy": "keep",
                "type": "enum('appels','emploi')",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "opinion_categories",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [
                    {
                        "table": "opinions",
                        "column": "opinion_category_id",
                        "selfReferential": false
                    }
                ],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "name",
                "strategy": "fake",
                "fakerMethod": "name",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité personnelle",
                    "name": "Nom complet",
                    "sensitivity": "high"
                }
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "opinions",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "title",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "description",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "images",
                "strategy": "keep",
                "type": "json",
                "detectedMatcher": null
            },
            {
                "name": "image_caption",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "content",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "opinion_category_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "reading_time",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "password_reset_tokens",
        "columns": [
            {
                "name": "email",
                "strategy": "fake",
                "fakerMethod": "safeEmail",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Coordonnées de contact",
                    "name": "Adresse email",
                    "sensitivity": "high"
                }
            },
            {
                "name": "token",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "post_categories",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [
                    {
                        "table": "post_post_category",
                        "column": "post_category_id",
                        "selfReferential": false
                    },
                    {
                        "table": "video_post_category",
                        "column": "post_category_id",
                        "selfReferential": false
                    }
                ],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "name",
                "strategy": "fake",
                "fakerMethod": "name",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité personnelle",
                    "name": "Nom complet",
                    "sensitivity": "high"
                }
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "post_post_category",
        "columns": [
            {
                "name": "post_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "post_category_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "post_views",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "viewable_id",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            },
            {
                "name": "viewable_type",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "session_id",
                "strategy": "null",
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Identité numérique",
                    "name": "Identifiant de session",
                    "sensitivity": "high"
                }
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "posts",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [
                    {
                        "table": "post_post_category",
                        "column": "post_id",
                        "selfReferential": false
                    }
                ],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "title",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "description",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "images",
                "strategy": "keep",
                "type": "json",
                "detectedMatcher": null
            },
            {
                "name": "image_caption",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "content",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "reading_time",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "pubs",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "organisation_name",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "organisation_description",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "url",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "images",
                "strategy": "keep",
                "type": "json",
                "detectedMatcher": null
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "horizontal",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "sessions",
        "columns": [
            {
                "name": "id",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "user_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "ip_address",
                "strategy": "fake",
                "fakerMethod": "ipv4",
                "fakerArguments": [],
                "type": "varchar(45)",
                "detectedMatcher": {
                    "groupLabel": "Identité numérique",
                    "name": "Adresse IP",
                    "sensitivity": "medium"
                }
            },
            {
                "name": "user_agent",
                "strategy": "keep",
                "type": "text",
                "detectedMatcher": null
            },
            {
                "name": "payload",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "last_activity",
                "strategy": "keep",
                "type": "int",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "users",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [
                    {
                        "table": "comments",
                        "column": "user_id",
                        "selfReferential": false
                    }
                ],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "username",
                "strategy": "fake",
                "fakerMethod": "userName",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Coordonnées de contact",
                    "name": "Nom d'utilisateur / Identifiant",
                    "sensitivity": "medium"
                }
            },
            {
                "name": "email",
                "strategy": "fake",
                "fakerMethod": "safeEmail",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Coordonnées de contact",
                    "name": "Adresse email",
                    "sensitivity": "high"
                }
            },
            {
                "name": "email_verified_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "password",
                "strategy": "static",
                "value": "REDACTED",
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Authentification et secrets",
                    "name": "Mot de passe / Secret",
                    "sensitivity": "critical"
                }
            },
            {
                "name": "remember_token",
                "strategy": "keep",
                "type": "varchar(100)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "role",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            }
        ]
    },
    {
        "name": "video_post_category",
        "columns": [
            {
                "name": "video_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "post_category_id",
                "strategy": "keep",
                "type": "bigint unsigned",
                "detectedMatcher": null
            }
        ]
    },
    {
        "name": "videos",
        "columns": [
            {
                "name": "id",
                "strategy": "remapping",
                "remappingUse": "random_integer",
                "foreignKeys": [
                    {
                        "table": "video_post_category",
                        "column": "video_id",
                        "selfReferential": false
                    }
                ],
                "type": "bigint unsigned",
                "detectedMatcher": null
            },
            {
                "name": "title",
                "strategy": "fake",
                "fakerMethod": "jobTitle",
                "fakerArguments": [],
                "type": "varchar(255)",
                "detectedMatcher": {
                    "groupLabel": "Informations professionnelles et emploi",
                    "name": "Intitulé de poste",
                    "sensitivity": "low"
                }
            },
            {
                "name": "slug",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "description",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "youtube_url",
                "strategy": "keep",
                "type": "varchar(255)",
                "detectedMatcher": null
            },
            {
                "name": "video_caption",
                "strategy": "keep",
                "type": "mediumtext",
                "detectedMatcher": null
            },
            {
                "name": "content",
                "strategy": "keep",
                "type": "longtext",
                "detectedMatcher": null
            },
            {
                "name": "reading_time",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "online",
                "strategy": "keep",
                "type": "tinyint(1)",
                "detectedMatcher": null
            },
            {
                "name": "created_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            },
            {
                "name": "updated_at",
                "strategy": "keep",
                "type": "timestamp",
                "detectedMatcher": null
            }
        ]
    }
];
