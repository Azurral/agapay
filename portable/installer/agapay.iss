; AGAPAY for Windows: the installer (Inno Setup 6). portable\build.ps1 compiles it after building
; portable-build\AGAPAY\system, passing /DAppVersion=<version>.
;
; C:\AGAPAY gets:  AGAPAY (opens the app), Backup now, Restore a backup, AGAPAY Backups\, Uninstall AGAPAY,
;                  AGAPAY User Guide.pdf, system\ (hidden: PHP, Apache, MariaDB, the program and the records)
; plus one AGAPAY icon on the desktop, and AGAPAY starts with Windows. No Start menu entries.

#ifndef AppVersion
  #define AppVersion "0.0.0"
#endif
#define Tools "{app}\system\tools"
#define Runner "{sys}\wscript.exe"

[Setup]
AppId={{B9E3849C-EB7E-4493-8F34-F391689B2F7F}
AppName=AGAPAY
AppVersion={#AppVersion}
AppVerName=AGAPAY {#AppVersion}
AppPublisher=AGAPAY capstone team
VersionInfoVersion={#AppVersion}
DefaultDirName=C:\AGAPAY
DisableDirPage=yes
DisableProgramGroupPage=yes
DisableWelcomePage=no
PrivilegesRequired=lowest
UsedUserAreasWarning=no
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0
WizardStyle=modern
WizardImageFile=wizard-image-1x.bmp,wizard-image-2x.bmp
WizardSmallImageFile=wizard-small-58.bmp,wizard-small-116.bmp
SetupIconFile=..\template\tools\agapay.ico
UninstallDisplayIcon={app}\system\tools\agapay.ico
UninstallDisplayName=AGAPAY
UninstallFilesDir={app}\system\uninstall
OutputDir=..\..\portable-build
OutputBaseFilename=AGAPAY-Setup-v{#AppVersion}
Compression=lzma2/max
SolidCompression=yes
CloseApplications=no

[Messages]
WelcomeLabel1=Welcome to AGAPAY
WelcomeLabel2=This installs AGAPAY {#AppVersion}, the beneficiary and intervention system of the Office of the Municipal Agriculturist, Bontoc, on this laptop.%n%nEverything goes into one folder, C:\AGAPAY, with an AGAPAY icon on the desktop. AGAPAY starts by itself with Windows and makes a backup every day.%n%nIt works without the internet; it only goes online when the Administrator installs an update.
FinishedLabel=AGAPAY is installed.%n%nOpen it any time with the AGAPAY icon on the desktop or in the C:\AGAPAY folder. Sign in with the Administrator account you just made, then add the other staff in User Management.

[Dirs]
Name: "{app}\system"; Attribs: hidden
Name: "{app}\AGAPAY Backups"; Flags: uninsneveruninstall

[InstallDelete]
; A reinstall replaces the program completely; the records (system\data) and the backups stay.
Type: filesandordirs; Name: "{app}\system\app"
Type: filesandordirs; Name: "{app}\system\app-previous"

[Files]
Source: "..\..\portable-build\AGAPAY\system\*"; DestDir: "{app}\system"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\..\handover\AGAPAY-User-Guide.pdf"; DestDir: "{app}"; DestName: "AGAPAY User Guide.pdf"; Flags: ignoreversion skipifsourcedoesntexist

[Icons]
Name: "{app}\AGAPAY"; Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" open.ps1"; IconFilename: "{#Tools}\agapay.ico"; Comment: "Open AGAPAY"
Name: "{app}\Backup now"; Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" backup.ps1 -Reason manual"; IconFilename: "{#Tools}\backup.ico"; Comment: "Save a backup of AGAPAY now"
Name: "{app}\Restore a backup"; Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" restore.ps1"; IconFilename: "{#Tools}\restore.ico"; Comment: "Put back an AGAPAY backup"
Name: "{app}\Uninstall AGAPAY"; Filename: "{uninstallexe}"; Comment: "Remove AGAPAY from this laptop"
Name: "{autodesktop}\AGAPAY"; Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" open.ps1"; IconFilename: "{#Tools}\agapay.ico"; Comment: "Open AGAPAY"
Name: "{userstartup}\AGAPAY"; Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" start.ps1"; IconFilename: "{#Tools}\agapay.ico"; Comment: "Start AGAPAY with Windows"

[Run]
Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" open.ps1"; Description: "Open AGAPAY now"; Flags: postinstall nowait skipifsilent; Check: SetupSucceeded

[UninstallRun]
Filename: "{#Runner}"; Parameters: """{#Tools}\run.vbs"" uninstall.ps1"; Flags: runhidden waituntilterminated; RunOnceId: "StopAgapay"

[Code]
const
  MetColor = $004E8F1F;      { #1F8F4E }
  UnmetColor = $00808080;
  BadColor = $004545D6;      { #D64545 }

var
  AccountPage: TWizardPage;
  LastNameEdit, FirstNameEdit, MiddleEdit, UsernameEdit, PasswordEdit, ConfirmEdit: TNewEdit;
  SuffixCombo: TNewComboBox;
  LengthRule, MatchRule, UsernameHint: TNewStaticText;
  Succeeded: Boolean;
  DeleteRecords: Boolean;

function AppFolder: String;
begin
  Result := WizardDirValue;
end;

{ An existing copy (records already there): the account page is skipped and the records are kept. }
function IsReinstall: Boolean;
begin
  Result := FileExists(AppFolder + '\system\data\agapay.env');
end;

{ Unattended installs (tests): AGAPAY-Setup.exe /VERYSILENT /ACCOUNT=<json file> instead of the account page. }
function AccountParam: String;
begin
  Result := ExpandConstant('{param:ACCOUNT|}');
end;

function SetupSucceeded: Boolean;
begin
  Result := Succeeded;
end;

function IsUsernameValid(S: String): Boolean;
var
  I: Integer;
begin
  Result := (Length(S) >= 1) and (Length(S) <= 50);
  for I := 1 to Length(S) do
    if not (((S[I] >= 'A') and (S[I] <= 'Z')) or ((S[I] >= 'a') and (S[I] <= 'z')) or ((S[I] >= '0') and (S[I] <= '9'))
            or (S[I] = '_') or (S[I] = '.') or (S[I] = '-')) then
      Result := False;
end;

function IsLetter(C: Char): Boolean;
begin
  Result := ((C >= 'A') and (C <= 'Z')) or ((C >= 'a') and (C <= 'z'));
end;

function IsInitialValid(S: String): Boolean;
begin
  S := Trim(S);
  Result := (S = '') or ((Length(S) = 1) and IsLetter(S[1])) or ((Length(S) = 2) and IsLetter(S[1]) and (S[2] = '.'));
end;

procedure ShowRule(Rule: TNewStaticText; Met: Boolean; Text: String);
begin
  if Met then begin
    Rule.Caption := #$2713 + '  ' + Text;
    Rule.Font.Color := MetColor;
  end else begin
    Rule.Caption := #$2022 + '  ' + Text;
    Rule.Font.Color := UnmetColor;
  end;
end;

function AccountReady: Boolean;
begin
  Result := (Trim(LastNameEdit.Text) <> '') and (Trim(FirstNameEdit.Text) <> '') and IsInitialValid(MiddleEdit.Text)
    and IsUsernameValid(UsernameEdit.Text) and (Length(PasswordEdit.Text) >= 8) and (PasswordEdit.Text = ConfirmEdit.Text);
end;

procedure UpdateAccountPage(Sender: TObject);
begin
  ShowRule(LengthRule, Length(PasswordEdit.Text) >= 8, 'At least 8 characters');
  ShowRule(MatchRule, (PasswordEdit.Text <> '') and (PasswordEdit.Text = ConfirmEdit.Text), 'The two passwords match');
  if (UsernameEdit.Text <> '') and not IsUsernameValid(UsernameEdit.Text) then
    UsernameHint.Font.Color := BadColor
  else
    UsernameHint.Font.Color := UnmetColor;
  if WizardForm.CurPageID = AccountPage.ID then
    WizardForm.NextButton.Enabled := AccountReady;
end;

function AddLabel(Caption: String; Left, Top: Integer): TNewStaticText;
begin
  Result := TNewStaticText.Create(AccountPage);
  Result.Parent := AccountPage.Surface;
  Result.Caption := Caption;
  Result.Left := Left;
  Result.Top := Top;
end;

function AddEdit(Left, Top, Width: Integer): TNewEdit;
begin
  Result := TNewEdit.Create(AccountPage);
  Result.Parent := AccountPage.Surface;
  Result.Left := Left;
  Result.Top := Top + ScaleY(18);
  Result.Width := Width;
  Result.OnChange := @UpdateAccountPage;
end;

procedure InitializeWizard;
var
  Gap, Half, Right, Row: Integer;
begin
  AccountPage := CreateCustomPage(wpWelcome, 'Administrator account',
    'The Municipal Agriculturist signs in with this account. The other staff are added later in User Management.');
  Gap := ScaleX(16);
  Half := (AccountPage.SurfaceWidth - Gap) div 2;
  Right := Half + Gap;
  Row := ScaleY(50);

  AddLabel('Last name', 0, 0);
  LastNameEdit := AddEdit(0, 0, Half);
  LastNameEdit.MaxLength := 100;
  AddLabel('First name', Right, 0);
  FirstNameEdit := AddEdit(Right, 0, Half);
  FirstNameEdit.MaxLength := 100;

  AddLabel('M.I. (optional)', 0, Row);
  MiddleEdit := AddEdit(0, Row, ScaleX(90));
  MiddleEdit.MaxLength := 2;
  AddLabel('Suffix (optional)', ScaleX(106), Row);
  SuffixCombo := TNewComboBox.Create(AccountPage);
  SuffixCombo.Parent := AccountPage.Surface;
  SuffixCombo.Style := csDropDownList;
  SuffixCombo.Left := ScaleX(106);
  SuffixCombo.Top := Row + ScaleY(18);
  SuffixCombo.Width := Half - ScaleX(106);
  SuffixCombo.Items.Add('None');
  SuffixCombo.Items.Add('Jr.');
  SuffixCombo.Items.Add('Sr.');
  SuffixCombo.Items.Add('II');
  SuffixCombo.Items.Add('III');
  SuffixCombo.Items.Add('IV');
  SuffixCombo.Items.Add('V');
  SuffixCombo.ItemIndex := 0;

  AddLabel('Username', Right, Row);
  UsernameEdit := AddEdit(Right, Row, Half);
  UsernameEdit.MaxLength := 50;
  UsernameHint := AddLabel('No spaces: letters, numbers, . _ - only', Right, Row + ScaleY(42));
  UsernameHint.Font.Size := 8;

  AddLabel('Password', 0, Row * 2 + ScaleY(12));
  PasswordEdit := AddEdit(0, Row * 2 + ScaleY(12), Half);
  PasswordEdit.PasswordChar := '*';
  AddLabel('Type the password again', Right, Row * 2 + ScaleY(12));
  ConfirmEdit := AddEdit(Right, Row * 2 + ScaleY(12), Half);
  ConfirmEdit.PasswordChar := '*';

  LengthRule := AddLabel('', 0, Row * 3 + ScaleY(20));
  MatchRule := AddLabel('', Right, Row * 3 + ScaleY(20));
  UpdateAccountPage(nil);
end;

function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := (PageID = AccountPage.ID) and (IsReinstall or (AccountParam <> ''));
end;

procedure CurPageChanged(CurPageID: Integer);
begin
  if CurPageID = AccountPage.ID then
    UpdateAccountPage(nil);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if (CurPageID = AccountPage.ID) and not AccountReady then
    Result := False;
end;

function PowerShell: String;
begin
  Result := ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe');
end;

function RunTool(Script, Arguments: String): Integer;
var
  Code: Integer;
begin
  if not Exec(PowerShell, '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + AppFolder + '\system\tools\' + Script + '" ' + Arguments,
              '', SW_HIDE, ewWaitUntilTerminated, Code) then
    Code := -1;
  Result := Code;
end;

{ Reinstalling over a copy in use: back up the records first, then stop AGAPAY so its files can be replaced. }
function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  Result := '';
  if IsReinstall and FileExists(AppFolder + '\system\tools\backup.ps1') then begin
    if RunTool('backup.ps1', '-Quiet -Reason before-upgrade') <> 0 then begin
      Result := 'A backup of the current records could not be made, so nothing was changed. Restart the laptop and run AGAPAY-Setup.exe again.';
      Exit;
    end;
    RunTool('stop.ps1', '');
  end;
end;

function JsonText(S: String): String;
begin
  StringChangeEx(S, '\', '\\', True);
  StringChangeEx(S, '"', '\"', True);
  Result := '"' + S + '"';
end;

{ The account goes to setup.ps1 through a file in Setup's private temporary folder (never on a command line);
  agapay:install deletes it as soon as it has read it. }
function WriteAccountFile: String;
var
  Lines: TArrayOfString;
  Suffix: String;
begin
  Result := ExpandConstant('{tmp}\agapay-account.json');
  Suffix := '';
  if SuffixCombo.ItemIndex > 0 then
    Suffix := SuffixCombo.Text;
  SetArrayLength(Lines, 1);
  Lines[0] := '{"last_name": ' + JsonText(Trim(LastNameEdit.Text)) + ', "first_name": ' + JsonText(Trim(FirstNameEdit.Text))
    + ', "middle_initial": ' + JsonText(Trim(MiddleEdit.Text)) + ', "suffix": ' + JsonText(Suffix)
    + ', "username": ' + JsonText(Trim(UsernameEdit.Text)) + ', "password": ' + JsonText(PasswordEdit.Text) + '}';
  SaveStringsToUTF8File(Result, Lines, False);
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Arguments: String;
begin
  if CurStep = ssPostInstall then begin
    WizardForm.StatusLabel.Caption := 'Setting up AGAPAY. This takes about a minute...';
    WizardForm.ProgressGauge.Style := npbstMarquee;
    Arguments := '';
    if IsReinstall then
      Arguments := ''
    else if AccountParam <> '' then
      Arguments := '-AccountFile "' + AccountParam + '"'
    else
      Arguments := '-AccountFile "' + WriteAccountFile + '"';
    Succeeded := RunTool('setup.ps1', Arguments) = 0;
    WizardForm.ProgressGauge.Style := npbstNormal;
    if not Succeeded and not WizardSilent then
      MsgBox('AGAPAY was copied but could not be set up.' + #13#10#13#10
        + 'Restart the laptop and run AGAPAY-Setup.exe again. If it fails again, send the files in' + #13#10
        + AppFolder + '\system\data\logs to the developers.', mbError, MB_OK);
  end;
end;

{ Uninstall: the records and backups are kept unless the person asks (twice) to delete them. }
function InitializeUninstall: Boolean;
begin
  Result := True;
  DeleteRecords := False;
  if UninstallSilent then
    Exit;
  if MsgBox('Do you also want to DELETE all AGAPAY records and backups?' + #13#10#13#10
      + 'Choose No to keep them (recommended). Installing AGAPAY again brings them back.',
      mbConfirmation, MB_YESNO or MB_DEFBUTTON2) = IDYES then
    DeleteRecords := MsgBox('This permanently deletes every farmer record, report, photo and backup on this laptop.' + #13#10#13#10
      + 'Delete everything?', mbError, MB_YESNO or MB_DEFBUTTON2) = IDYES;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if (CurUninstallStep = usPostUninstall) and DeleteRecords then begin
    DelTree(ExpandConstant('{app}\system'), True, True, True);
    DelTree(ExpandConstant('{app}\AGAPAY Backups'), True, True, True);
    RemoveDir(ExpandConstant('{app}'));
  end;
end;
