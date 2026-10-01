 <!-- Main Sidebar Container -->
 <aside class="main-sidebar main-sidebar-custom sidebar-dark-primary elevation-4">
     <!-- Brand Logo -->
     <a href="#" class="brand-link">
         <img src="{{ asset('favicon.ico') }}" alt="AdminLTE Logo" class="brand-image" style="opacity: .8">
         <span class="brand-text font-weight-light"><strong>MODUL APP</strong></span>
     </a>

     <!-- Sidebar -->
     <div class="sidebar">
         <!-- Sidebar user (optional) -->
         <div class="user-panel mt-3 pb-3 mb-3 d-flex">
             <div class="info">
                 <a href="/dashboard" class="d-block"><strong>{{ strtoupper(auth()->user()->full_name)  }}</strong></a>
             </div>
         </div>
         <!-- Sidebar Menu -->
         <nav class="mt-2">
             <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                 data-accordion="false">
                 <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
                <li class="nav-item my-1=">
                    <a href="/absent" class="nav-link">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>Absent</p>
                    </a>
                </li>
                 <li class="nav-item">
                     <a href="/document" class="nav-link">
                         <i class="nav-icon fas fa-folder"></i>
                         <p>Document</p>
                     </a>
                 </li>
                <li class="nav-item">
                     <a href="#" class="nav-link {{ $active === 'quiz' ? 'active' : '' }}">
                         <i class="nav-icon fas fa-feather"></i>
                         <p>
                             Quiz
                             <i class="right fas fa-angle-left"></i>
                         </p>
                     </a>
                     <ul class="nav nav-treeview">
                        <li class="nav-item">
                             <a href="/quiz" class="nav-link">
                                 <i class="fas fa-plus nav-icon"></i>
                                 <p>Add Quiz</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/question" class="nav-link">
                                 <i class="fas fa-question nav-icon"></i>
                                 <p>Question</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/quiz/history" class="nav-link">
                                 <i class="fas fa-poll nav-icon"></i>
                                 <p>History</p>
                             </a>
                         </li>
                     </ul>
                 </li>
                 <li class="nav-item">
                     <a href="#" class="nav-link {{ $active === 'setting' ? 'active' : '' }}">
                         <i class="nav-icon fas fa-cogs"></i>
                         <p>
                             Settings
                             <i class="right fas fa-angle-left"></i>
                         </p>
                     </a>
                     <ul class="nav nav-treeview">
                        <li class="nav-item">
                             <a href="/user" class="nav-link">
                                 <i class="fas fa-user-cog nav-icon"></i>
                                 <p>User</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/divisi" class="nav-link">
                                 <i class="fas fa-city nav-icon"></i>
                                 <p>Divisi</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/subdivisi" class="nav-link">
                                 <i class="fas fa-building nav-icon"></i>
                                 <p>Sub Divisi</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/joblevel" class="nav-link">
                                 <i class="fas fa-briefcase nav-icon"></i>
                                 <p>Job Level</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/dokumentype" class="nav-link">
                                 <i class="fas fa-book nav-icon"></i>
                                 <p>Document Type</p>
                             </a>
                         </li>
                     </ul>
                 </li>
             </ul>
         </nav>
         <!-- /.sidebar-menu -->
     </div>
     <!-- /.sidebar -->

     <div class="sidebar-custom mb-3">
         <form action="/logout" method="POST">
             @csrf
             <button type="submit" class="btn btn-link"><i class="fas fa-sign-out-alt"></i> Log Out</button>
         </form>
     </div>
     <!-- /.sidebar-custom -->
 </aside>
