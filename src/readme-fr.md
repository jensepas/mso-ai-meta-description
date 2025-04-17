# MSO AI Meta Description

**Ajoutez facilement des méta descriptions personnalisables à votre site WordPress, avec des suggestions optionnelles basées sur l'IA.**

---

## 🧠 Introduction

**MSO AI Meta Description** est un plugin WordPress léger conçu pour vous donner un contrôle total sur les balises de méta description de votre site pour un meilleur SEO. Écrivez-les manuellement ou obtenez des suggestions en utilisant les derniers LLM comme Gemini, Mistral, Anthropic et OpenAI (ChatGPT).

---

## 📑 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Installation](#-installation)
- [Utilisation](#-utilisation)
- [Configuration](#-configuration-des-fonctionnalités-IA)
- [FAQ](#-questions-fréquentes)
- [Journal des modifications](#-journal-des-modifications)
- [Dépannage](#-dépannage)
- [Contributeurs](#-contributeurs)
- [Licence](#-licence)

---

## ✨ Fonctionnalités

- ✅ **Méta Descriptions Manuelles** pour les articles, pages, types de publications personnalisés et la page d'accueil.
- 🤖 **Suggestions IA (Optionnelles)** utilisant :
    - Google Gemini
    - Mistral AI
    - OpenAI (ChatGPT)
    - Anthropic Claude
    - Cohere
- 🧩 **Compteur de Caractères** pour rester dans la plage optimale de 120 à 160 caractères.
- ⚙️ **Support de Description de la Page d'Accueil** que vous utilisiez une page statique ou les derniers articles.
- 🪶 **Léger et Focus** : Fait bien une chose, sans surcharge.
- 🛠️ **Configuration Simple** avec chargement dynamique des modèles après l'entrée de la clé API.

---

## 🛠️ Installation

### Prérequis Minimums

- WordPress 6.0+
- PHP 8.1+

### Installation Automatique

1. Allez dans votre tableau de bord admin WordPress.
2. Naviguez vers **Extensions > Ajouter**.
3. Recherchez **"MSO AI Meta Description"**.
4. Cliquez sur **Installer maintenant** puis sur **Activer**.

### Installation Manuelle (Téléchargement)

1. Téléchargez le fichier zip du plugin (`mso-ai-meta-description.zip`).
2. Allez dans **Extensions > Ajouter > Téléverser une extension**.
3. Sélectionnez le fichier zip et installez-le.
4. Cliquez sur **Activer**.

### Installation Manuelle (FTP)

1. Décompressez le plugin.
2. Téléversez le dossier `mso-ai-meta-description` dans `/wp-content/plugins/`.
3. Allez dans **Extensions > Extensions installées** et **Activez-le**.

---

## 🚀 Utilisation

Après avoir activé le plugin :

- Allez sur n'importe quel article, page ou type de publication personnalisé.
- Faites défiler jusqu'à la boîte **MSO AI Meta Description**.
- Écrivez votre description personnalisée ou utilisez les boutons IA (si configurés).
- Enregistrez ou publiez l'article.

---

## ⚙️ Configuration des fonctionnalités IA

1. Naviguez vers **Réglages > Général**.
2. Faites défiler jusqu'à la section **MSO AI Meta Description**.
3. Entrez vos clés API pour OpenAI, Mistral, Gemini
4. Choisissez votre modèle préféré dans les listes déroulantes (par exemple, `gpt-3.5-turbo`, `mistral-small-latest`, `gemini-2.0-flash`).
5. Cliquez sur **Enregistrer les modifications**.

---

## ❓ Questions Fréquentes

### Où se trouvent les paramètres du plugin ?

Les paramètres se trouvent sous **Réglages > Général** dans votre tableau de bord admin WordPress.

### Ai-je besoin de clés API ?

Seulement si vous souhaitez utiliser la **génération de descriptions basée sur l'IA**. L'édition manuelle fonctionne sans clés API.

### Quels modèles sont supportés ?

Le plugin récupère dynamiquement les modèles disponibles une fois une clé API valide entrée. Les modèles par défaut populaires incluent :
- `gpt-3.5-turbo`
- `mistral-small-latest`
- `gemini-2.0-flash`
- `claude-3-sonnet-20240229`
- `command-a-03-2025`

### Cela va-t-il entrer en conflit avec des plugins SEO comme Yoast ou Rank Math ?

Possible. Les deux plugins peuvent générer une méta description. **Évitez les doublons** en désactivant les méta descriptions dans l'un des plugins.

### Comment définir la description de la page d'accueil ?

- **Page Statique** : Modifiez la page et utilisez la boîte de méta.
- **Derniers Articles** : Allez dans **Réglages > Lecture**, trouvez le champ “Description de la méta de la page d'accueil”.

---

## 🧾 Journal des modifications

### 1.4.0 – *2025-04-17*

- ✨ Ajout du support pour **Cohere**

### 1.3.0

- ✨ Ajout du support pour **Anthropic**

### 1.2.0

- ✨ Ajout du support pour **OpenAI (ChatGPT)**
- ⚙️ Meilleure gestion des erreurs pour tous les fournisseurs
- 🎨 Amélioration de l'interface utilisateur pour les réglages et l'éditeur
- 🛠️ Correction de la logique de visibilité des boutons IA

### 1.1.0

- 🧱 Refonte majeure de la base de code (structure basée sur SoC)
- 🎛️ Amélioration de l'expérience utilisateur de la page de réglages
- 📡 Standardisation des réponses API

### 1.0.0

- 🚀 Version initiale avec support pour Gemini et Mistral

---

## 🛠️ Dépannage

- **Méta Tags Dupliqués ?** Vérifiez si un autre plugin SEO les ajoute également. Désactivez-en un.
- **IA Non Fonctionnelle ?** Vérifiez que votre clé API est correcte et que la liste des modèles se charge correctement.
- **Description de la Page d'Accueil Manquante ?** Assurez-vous que le type de votre page d'accueil (statique vs derniers articles) correspond à l'emplacement d'entrée du plugin.

---

## 👥 Contributeurs

- **MS-ONLY** – [https://www.ms-only.fr](https://www.ms-only.fr)

---

## 📄 Licence

**MSO AI Meta Description** est sous licence [GPLv2 ou ultérieure](https://www.gnu.org/licenses/gpl-2.0.html).
