import React, { useCallback, useState } from 'react';
import Modal from '@/components/modal';
import Button from '@/components/form/button';
import {
    CopyIcon,
    DatabaseIcon,
    FolderIcon,
    FolderPlusIcon,
    ViewIcon,
} from 'lucide-react';
import Stepper, { StepperProps } from '@/components/stepper';
import Input from './form/input';
import Textarea from './form/textarea';
import SelectFolderModal from './select-folder-modal';
import { Tabs, TabsList, TabsTrigger } from './ui/tabs';
import { RadioGroup, RadioGroupItem } from './ui/radio-group';
import { Field, FieldContent, FieldLabel, FieldTitle } from './ui/field';
import DatabaseIconHelper from './database-icon-helper';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from './reui/frame';

export default function CreateProjectModal() {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                onClick={() => setOpen(true)}
                variant="outline"
                size="sm"
                icon={FolderPlusIcon}
            >
                Créer un projet
            </Button>
            <Modal
                showHeader
                className="h-auto"
                open={open}
                onOpenChange={setOpen}
                title="Créer un nouveau projet"
                description="Formulaire pour créer un nouveau projet"
            >
                <CreateProjectContent />
            </Modal>
        </>
    );
}

const databases = [
    {
        id: 'mysql',
        name: 'MySQL',
    },
    {
        id: 'mariadb',
        name: 'MariaDB',
    },
    {
        id: 'postgresql',
        name: 'PostgreSQL',
    },
    {
        id: 'sqlite',
        name: 'SQLite',
    },
];



const ProjectInfoForm = () => {

    const selectFolder = async () => {
        const folder = await SelectFolderModal.call({
            onlyFolders: true
        });
        console.log(folder);
    };


    return <div className="grid grid-cols-2 gap-4">
        <SelectFolderModal />
        <Input
            label="Nom du projet"
            placeholder="Ex: Mon super projet"
        />
        <Input
            label="Séléctionner un dossier"
            placeholder="Séléctionner un dossier"
            readOnly
            button={{
                variant: 'outline',
                icon: FolderIcon,
                tooltip: 'Séléctionner un dossier',
                onClick: selectFolder
            }}
        />
        <Textarea
            label="Description du projet"
            placeholder="Ex: Un projet pour gérer mes tâches"
            className="col-span-2 max-h-52"
        />
    </div>
}


const CreateProjectContent = () => {

    return (
        <div className="flex h-full flex-col gap-4 p-4">
            <Frame>
                <FrameHeader>
                    <FrameTitle>Informations du projet</FrameTitle>
                    <FrameDescription>
                        Remplissez les informations de base pour créer un nouveau projet.
                    </FrameDescription>
                </FrameHeader>
                <FramePanel>
                    <ProjectInfoForm />
                </FramePanel>
            </Frame>
            <Frame>
                <FrameHeader>
                    <FrameTitle>Base de données</FrameTitle>
                    <FrameDescription>
                        Sélectionnez le type de base de données pour votre projet.
                    </FrameDescription>
                </FrameHeader>
                <FramePanel>
                    <div className="flex flex-col gap-4">
                        <RadioGroup defaultValue="mysql" className="flex gap-2">
                            {databases.map((db) => (
                                <FieldLabel
                                    key={db.id}
                                    htmlFor={db.id}
                                >
                                    <Field orientation="horizontal">
                                        <FieldContent>
                                            <FieldTitle className="flex flex-col items-start">
                                                <DatabaseIconHelper databaseType={db.id as any} className="text-lg" />
                                                <span className="text-sm font-semibold hidden sm:inline">
                                                    {db.name}
                                                </span>
                                            </FieldTitle>
                                        </FieldContent>
                                        <RadioGroupItem value={db.id} id={db.id} />
                                    </Field>
                                </FieldLabel>
                            ))}
                        </RadioGroup>
                        <Tabs defaultValue="overview" className="w-full mb-4 justify-center items-center">
                            <TabsList>
                                <TabsTrigger value="overview">
                                    <DatabaseIcon />
                                    Se connecter
                                </TabsTrigger>
                                <TabsTrigger value="analytics">
                                    <CopyIcon />
                                    Cloner
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </FramePanel>
            </Frame>
            
            <div className="flex justify-end gap-2">
                <Button variant="outline">
                    Annuller
                </Button>
                <Button>
                    Suivant
                </Button>
            </div>
        </div>
    );
};