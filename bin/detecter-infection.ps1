<#
    Détection (LECTURE SEULE) des traces du code malveillant injecté dans le dépôt
    (tâche VS Code « runOn: folderOpen » qui lance node sur une fausse police .orc,
    commits 49892e3 / 8b26f7d puis réinjection 78bc3b9 du 26/10/2026).

    Ce script ne modifie, ne supprime et n'exécute rien : il liste ce qu'il trouve.
    Un résultat « propre » ne prouve pas qu'un poste est sain ; un résultat positif
    signifie : traiter le poste comme compromis (voir docs/integrite-depot.md).

    Usage (Windows) :
        powershell -ExecutionPolicy Bypass -File bin\detecter-infection.ps1
        powershell -ExecutionPolicy Bypass -File bin\detecter-infection.ps1 -Chemins C:\dev,D:\projets
#>
param(
    # Dossiers où chercher des copies du projet (par défaut : le profil utilisateur).
    [string[]] $Chemins = @($env:USERPROFILE)
)

$ErrorActionPreference = 'SilentlyContinue'
$alertes = New-Object System.Collections.Generic.List[string]
$ignores = '\\(node_modules|vendor|\.git|AppData\\Local\\Temp)\\'

Write-Host "Recherche dans : $($Chemins -join ', ')" -ForegroundColor Cyan

# 1. Fausses polices (.orc) et dossier de leurres.
foreach ($chemin in $Chemins) {
    Get-ChildItem -Path $chemin -Recurse -Force -File -Filter '*.orc' |
        Where-Object { $_.FullName -notmatch $ignores } |
        ForEach-Object { $alertes.Add("Charge utile probable : $($_.FullName)") }
}

# 2. Tâches VS Code lancées à l'ouverture d'un dossier.
foreach ($chemin in $Chemins) {
    Get-ChildItem -Path $chemin -Recurse -Force -File -Include 'tasks.json', 'settings.json' |
        Where-Object { $_.DirectoryName -match '\\\.vscode$' -and $_.FullName -notmatch $ignores } |
        ForEach-Object {
            $contenu = Get-Content -Raw -LiteralPath $_.FullName
            if ($contenu -match 'folderOpen' -or $contenu -match '"task\.allowAutomaticTasks"\s*:\s*(true|"on")') {
                $alertes.Add("Exécution automatique à l'ouverture : $($_.FullName)")
            }
        }
}

# 3. Réglage VS Code de l'utilisateur.
$userSettings = Join-Path $env:APPDATA 'Code\User\settings.json'
if (Test-Path $userSettings) {
    $contenu = Get-Content -Raw -LiteralPath $userSettings
    if ($contenu -match '"task\.allowAutomaticTasks"\s*:\s*(true|"on")') {
        $alertes.Add("VS Code autorise les tâches automatiques : $userSettings")
    }
}

# 4. Processus node en cours qui exécutent un .orc ou un fichier de « police ».
Get-CimInstance Win32_Process -Filter "Name = 'node.exe'" |
    Where-Object { $_.CommandLine -match '\.orc|fonts[\\/]fa-' } |
    ForEach-Object { $alertes.Add("Processus suspect (PID $($_.ProcessId)) : $($_.CommandLine)") }

# 5. Démarrage automatique (clés Run, tâches planifiées) pointant vers node ou un .orc.
foreach ($cle in 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run', 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Run') {
    $valeurs = Get-ItemProperty -Path $cle
    if ($valeurs) {
        $valeurs.PSObject.Properties |
            Where-Object { $_.Name -notlike 'PS*' -and "$($_.Value)" -match 'node(\.exe)?\b|\.orc' } |
            ForEach-Object { $alertes.Add("Démarrage automatique ($cle) : $($_.Name) = $($_.Value)") }
    }
}
Get-ScheduledTask |
    Where-Object { ($_.Actions | ForEach-Object { "$($_.Execute) $($_.Arguments)" }) -match 'node(\.exe)?\b|\.orc' } |
    ForEach-Object { $alertes.Add("Tâche planifiée : $($_.TaskPath)$($_.TaskName)") }

Write-Host ''
if ($alertes.Count -eq 0) {
    Write-Host '[OK] Aucune trace connue trouvée.' -ForegroundColor Green
    Write-Host "Si vous avez ouvert le projet dans VS Code entre le 22/10 et aujourd'hui, suivez quand même les consignes de docs/integrite-depot.md."
    exit 0
}

Write-Host "[ALERTE] $($alertes.Count) trace(s) trouvée(s) :" -ForegroundColor Red
$alertes | ForEach-Object { Write-Host " - $_" -ForegroundColor Yellow }
Write-Host ''
Write-Host "Ne supprimez rien vous-même avant d'avoir prévenu l'équipe : considérez ce poste comme compromis."
Write-Host "Changez vos mots de passe et jetons DEPUIS UN AUTRE APPAREIL (voir docs/integrite-depot.md)."
exit 1
