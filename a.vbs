'==================================================================
' Script de Enterprise Architect (VBScript)
' DIAGRAMA DE SECUENCIA - CU01 Iniciar y Cerrar Sesion
' Capas: Vista -> Controlador -> Service -> Repositorio -> BD
'
' Solo el CAMINO EXITOSO (timeline limpio).
' Las ramas de error van DENTRO de los fragmentos 'alt' que
' agregas a mano (EA no permite crear 'alt' por script).
'
' USO:
' 1. Scripting -> Scripts -> tu grupo -> New VBScript
' 2. Pega TODO y Save Script
' 3. En el Browser SELECCIONA el paquete destino
' 4. Clic derecho sobre el script -> Run Script
'==================================================================

option explicit

!INC Local Scripts.EAConstants-VBScript

Dim oDiagram
Dim oPackage

Sub Main

    Set oPackage = Repository.GetTreeSelectedPackage()
    If oPackage Is Nothing Then
        Session.Prompt "Selecciona primero un paquete en el Browser.", 0
        Exit Sub
    End If

    ' ---------- Crear el diagrama de Secuencia ----------
    Set oDiagram = oPackage.Diagrams.AddNew("CU01 - Secuencia Iniciar y Cerrar Sesion", "Sequence")
    oDiagram.Update
    oPackage.Diagrams.Refresh

    ' ---------- Crear los participantes (lifelines) ----------
    Dim nombres(5)
    nombres(0) = "Usuario"
    nombres(1) = "Vista (LoginView)"
    nombres(2) = "Controlador (AuthController)"
    nombres(3) = "Service (AuthService)"
    nombres(4) = "Repositorio (prisma.usuario)"
    nombres(5) = "BD (PostgreSQL)"

    Dim els(5)
    Dim i
    For i = 0 To 5
        Dim el
        If i = 0 Then
            Set el = oPackage.Elements.AddNew(nombres(i), "Actor")
        Else
            Set el = oPackage.Elements.AddNew(nombres(i), "Sequence")
        End If
        el.Update
        Set els(i) = el
    Next
    oPackage.Elements.Refresh

    ' ---------- Colocar los participantes en el diagrama ----------
    For i = 0 To 5
        Dim dobj
        Set dobj = oDiagram.DiagramObjects.AddNew("l=" & (40 + i * 175) & ";r=" & (130 + i * 175) & ";t=-20;b=-70", "")
        dobj.ElementID = els(i).ElementID
        dobj.Sequence = i + 1
        dobj.Update
    Next
    oDiagram.DiagramObjects.Refresh

    ' ---------- INICIAR SESION ----------
    ' AddMsg origen, destino, numero, texto
    AddMsg els(0), els(1), 1,  "ingresar(correo, password)"
    AddMsg els(1), els(2), 2,  "login(dto)"
    AddMsg els(2), els(3), 3,  "login(dto)"
    AddMsg els(3), els(4), 4,  "findUnique({ where: { correo } })"
    AddMsg els(4), els(5), 5,  "SELECT * FROM Usuario WHERE correo = ?"
    AddMsg els(5), els(4), 6,  "return(usuario)"
    AddMsg els(4), els(3), 7,  "return(usuario)"
    AddMsg els(3), els(3), 8,  "bcrypt.compare(password, usuario.password)"
    AddMsg els(3), els(3), 9,  "buildSession(usuario)"
    AddMsg els(3), els(3), 10, "jwtService.sign({ sub, email, role })"
    AddMsg els(3), els(2), 11, "return({ accessToken, user })"
    AddMsg els(2), els(1), 12, "return({ accessToken, user })"
    AddMsg els(1), els(0), 13, "redirige al panel principal"

    ' ---------- CERRAR SESION (lado cliente) ----------
    AddMsg els(0), els(1), 14, "cerrarSesion()"
    AddMsg els(1), els(1), 15, "eliminarToken()"
    AddMsg els(1), els(0), 16, "redirige a Login"

    Repository.ReloadDiagram oDiagram.DiagramID
    Repository.OpenDiagram oDiagram.DiagramID

    Session.Prompt "Diagrama CU01 creado (camino exitoso)." & vbCrLf & _
                   "Agrega los 2 fragmentos 'alt' a mano y mete las ramas de error.", 0

End Sub

'------------------------------------------------------------------
' Crea un mensaje (connector Sequence) entre dos elementos
'   origen = destino -> mensaje reflexivo (auto-llamada)
'------------------------------------------------------------------
Sub AddMsg(oOrigen, oDestino, nNum, sTexto)
    Dim con
    Set con = oOrigen.Connectors.AddNew(sTexto, "Sequence")
    con.SupplierID = oDestino.ElementID
    con.SequenceNo = nNum
    con.Update
    oOrigen.Connectors.Refresh
End Sub

Main