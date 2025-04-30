
# TargetPlan
TargetPlan est un plugin pour I@nseo.
- https://www.ianseo.net/
- https://www.facebook.com/ianseoarchery 

Ce plugin permet l'affectation des archers aux cibles de façon graphique. 

## Principes généraux
Il est possible de faire l'affectation des cibles depuis les qualifications jusqu'aux phases finales.
Il intègre egalement des controles lors de l'affecation.
- Nombre d'archers par cible
- Contrôles de distances (TAE / Beursault)
- Nombre d'archers d'une même structure


## Installation

### Télécharger le fichier Zip depuis ce site (dépôt Git)
[Retrouvez le lien ici](https://github.com/protheus6/IanseoModules/archive/refs/heads/main.zip).  
![Etape 1: Télécharger le fichier](/Docs/Install/etape1.png)  


### Extraire les fichiers sur votre ordinateur.  
Au clic droit sur le fichier précédemment téléchargé, vous devez extraire les données avec "Extraire tout"  
![Étape 2: Extraire le fichier](/Docs/Install/etape2.1.png)  
Puis cliquer sur Extraire  
![Étape 2: Extraire le fichier](/Docs/Install/etape2.2.png) 
Les fichiers sont en cours d'extraction
![Étape 2: Extraire le fichier](/Docs/Install/etape2.3.png)  

![Etape 2: Extraire le fichier](/Docs/Install/etape2.3.png)  
Ensuite vous allez ouvrir le fichier IanseoModules-main, ainsi que les sous menu pour arriver au dossier TargetPlan et menu.php 

![Étape 2: Extraire le fichier](/Docs/Install/etape2.4.png) 
puis 
![Étape 2: Extraire le fichier](/Docs/Install/etape2.5.png)  
puis  
![Étape 2: Extraire le fichier](/Docs/Install/etape2.6.png)  
enfin selectionnez les deux éléments et au clic droit copier  
![Étape 2: Extraire le fichier](/Docs/Install/etape2.7.png)  

### Coller ces fichiers dans le répertoire de I@nseo  
 Pour ce faire, il vous faut copier ces deux éléments dans le menu Custom des modules de I@nseo.
 > *Chemin généralement utilisé par defaut:*\
 > C:\ianseo\htdocs\Modules\Custom  

![Etape 3: Coller le fichier](/Docs/Install/etape3.png)  

### Redémarrer le serveur Apache
![Etape 4 : Redémarrer Apache](/Docs/Install/etape4.png)  
Enfin, profiter pleinement de ce module TargetPlan ! 

## Mise à jour de TargetPlan. 
Pour le moment, il n'existe pas de mise à jour automatique de ce module.  
Lorsque vous mettez à jour I@nseo, ce module n'est pas effacé mais n'est pas non plus mis à jour. 
Il est necessaire de répéter les étapes d'installation afin de mettre à jour TargetPlan.

# Accès au module
Dans I@nseo, vous retrouverez TargetPlan depuis le menu Module > TargetPlan > Qualifications / Finales\
![Suppression Affectation](Docs/image-0.png)

## Qualifications
![Suppression Affectation](Docs/TargetPlan_home.png)
### Informations sur le départ
Dans la partie haute, on retrouve la compétition sélectionnée ainsi que le choix des départs issus de la compétition

### Type d'affichage du mur de tir
Le plan de cible peut être regroupé de la façon suivante:
- par blasons
- par catégories
### Archers à affecter
Dans la barre latérale gauche, vous pouvez retrouver les archers de votre départ classés comme indiqués précédemment.
### Zone d'affectation (cibles)
Il est également possible de personnaliser cet affichage. On peut afficher/masquer le detail des cibles (ne pas voir l'affectation). 
Enfin, il est possible de faire un filtre sur les archers restant à affecter.

### Visualisation des structures
Lors de chaque ouverture de TargetPlan, le module affectera une couleur differentes à chaque structure. Cette couleur est générée aléatoirement. C'est à dire qu'elle varie à chaque ouverture de TargetPlan.

### Affectation des archers aux cibles
Pour affecter un particpant à une cible, il convient de faire glisser le nom de l'archer sur la lettre de la cible souhaitée.
Le blason s'affichera alors sur la cible, indiquant que la modification est bien prise en compte. 

Si vous affectez un archer sur une place déjà occupée par un autre acher, TargetPlan désaffectera le premier archer déjà affecté pour le remplacer par l'archer en cours d'affectation.
![Suppression Affectation](Docs/image-8.png)

### Désaffectation d'un archer
Pour supprimer l'affectation d'un archer, il suffit simplement de faire un glisser déposer de l'archer en dehors du cadre de la cible pour désaffecter l'archer.

### Désaffectation en masse
Il est également possible de supprimer l'affectation d'une cible entière. Pour cela, il vous suffit de cliquer en haut à droite de la cible pour supprimer ces archers affectés.\
![Suppression Affectation](Docs/image-7.png)

### Survol au-dessus des archers. 
En survolant la somme des blasons nécessaires, vous pouvez voir l'ensemble des archers nécessitant ce type de blasons.
À la sélection d'un archer déjà affecté ou bien en cours d'affectation, vous pouvez également visualiser l'ensemble des archers de cette même structure.


### Contrôles réalisés
Lors de l'affectation des archers aux cibles plusieurs contrôles sont effectués rendant l'affectation impossible ou soulevant des alertes visuelles.
- Libre
- Complet
- Structure Unique
- Majoritaire
- Conflits distances 


|Nombre d'archers par cible | Nombre d'archers par cible |
| :----: | :----: |
| Un seul archer par cible |Une seule structure est présente sur la cible
| ![Un seul archer par cible](Docs/image-1.png) |![Plusieurs archer de la même structure sur la cible](Docs/image-5.png) |

|Nombre d'archers par cible |
| :----: |
| Une majorité d'archers sur une même cible d'une même structure |
| ![Une structure est majoritaire sur la cible](Docs/image-3.png) |

| Distances incohérentes sur la même cible | 
| :----: |
| Plusieurs archers avec des distances de tir différentes. | 
| ![Melanges de distances sur une cible](Docs/image-4.png) |

### Répartition des archers par blasons
En fonction de l'affectation, TargetPlan vous informe de la répartition des archers par type de blasons. 
![Recap Blasons](Docs/image-6.png)

# Retour à I@nseo
Une fois votre mur de plan de cible réalisé, vous disposez de la flèche en haut à gauche pour retourner sur votre compétition I@nseo.

## Finales
En cours de rédaction.


# English version
## IanseoModules
Modules for Ianseo Project :
https://www.ianseo.net/
https://www.facebook.com/ianseoarchery

	-> Target Plan (menu: Modules->Target Plan)
		Manage competitor placement with a drag and drop graphical interface.