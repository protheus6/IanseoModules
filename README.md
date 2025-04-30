
# TargetPlan
TargetPlan est un plugin pour I@nseo.
- https://www.ianseo.net/
- https://www.facebook.com/ianseoarchery 

Ce plugin permet l'affectation des archers aux cibles de façon graphique. 

## Principes généraux
Il est possible de faire l'affectation des cibles depuis les qualifications jusqu'au phases finales.
Il intègre egalement des controles lors de l'affecation.
- Nombre de archers par cible
- Controles de distances (TAE / Beursault)
- Nombre de archers d'une même structure

## Installation
1. Étape 1:
Télécharger depuis ce dépot le fichier Zip 

2. Étape 2:
Extraire les fichiers sur votre orfinateur

3. Etape 3
Déplacer par copier/coller le dossier TargetPlan et le fichier menu.php dans le répertoire d'installation de I@nseo. 
Par défaut, le répertoire est le suivant:
Xxxxxxxwxxxxxxxxxxxxxxxxx

4. Etape 4
Redémarrer le serveur Apache de votre ordinateur

5. Étape 5
Profiter pleinement de ce module TargetPlan ! 

# Retour à I@nseo
Une fois votre mur de plan de cible réalisés, vous disposez de la flèche en haut à gauche pour retourner sur votre compétition I@nseo.



## Accès au module
Dans I@nseo, vous retrouverez TargetPlan depuis le menu Module > TargetPlan > Qualifications / Finales

## Qualifications
Le module se présente de la facon suivante:
Une page par départ avec un regroupement soit:
- par blasons
- par catégories

Dans la barre latéral gauche, vous pouvez retrouver les archers de votre départ classés comme indiqués précédement.

Il est également possible de personaliser cet affichage. On peut afficher/masquer le details cibles (ne pas voir l'affectation). 
Enfin il est possible de faire un filtre sur les archers restant à affecter.

### Visualisation des structures
Lors de chaque ouverture de TargetPlan, le module affectera une couleur differentes à chaque structure. Cette couleur est générée aléatoirement. C'est à dire qu'elle varie à chaque ouverture de TargetPlan.

### Affectation des archers aux cibles
Pour affecter un particpant à une cible, il convient de faire glisser le nom de l'archer sur la lettre de la cible souhaitée.
Le blason s'affichera alors sur la cible, indiquant que la modification est bien prise en compte. 

### Survol au dessus des archers. 
En survolant la sommes des blasons necessaire vous pouvez voir l'ensemble des archers necessitant ce type de blasons.
À la selection d'un archer déjà affecté ou bien en cours d'affectation vous pouvez egalement visualiser l'ensemble des archers de cette même structure.

### Desaffectation en masse
Il est également possible de supprimer l'affectation d'une cible entière. Pour cela, il vous suffit de cliquer en haut à droite de la cible avec de supprimer ces archers affectés.\
![Suppression Affectation](Docs/image-7.png)



### Controles réalisés
Lors de l'affectation des archers aux cible plusieurs controles sont effectué rendant l'affectation impossible ou soulevant des alertes visuelles.
- Libre
- Complet
- Structure Unique
- Majoritaire
- Conflits distances 

#### Nombre de archers par cible
un seul archer par cible\
![Un seul archer par cible](Docs/image-1.png)

#### Nombre de particpants d'une meme structure par cible
Une seule structure est présente sur la cible\
![Plusieurs archer de la même structure sur la cible](Docs/image-5.png)\
Une majorité de archers sur une meme cible d'une meme structure\
![Une structure est majoritaire sur la cible](Docs/image-3.png)\

#### Distances incohérantes sur la même cible
Plusieurs archers avec des distances de tir differents.\
![Melanges de distances sur une cible](Docs/image-4.png)

### répartition des archers par blasons
En fonction de l'affectation TargetPlan vous informes de la répartition des archers par type de blasons. 
![Recap Blasons](Docs/image-6.png)


## Finales
En cours de rédaction.


# English version
## IanseoModules
Modules for Ianseo Project :
https://www.ianseo.net/
https://www.facebook.com/ianseoarchery

	-> Target Plan (menu: Modules->Target Plan)
		Manage competitor placement with a drag and drop graphical interface.