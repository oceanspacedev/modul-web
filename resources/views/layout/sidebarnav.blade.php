 <!-- Main Sidebar Container -->
 <aside class="main-sidebar main-sidebar-custom sidebar-dark-primary elevation-4">
     <!-- Brand Logo -->
     <a href="/video" class="brand-link">
         <span class="d-inline-flex align-items-center justify-content-center bg-primary rounded-circle mr-2" style="width: 34px; height: 34px;">
             <i class="fas fa-layer-group text-white" style="font-size: 0.95rem;"></i>
         </span>
         <span class="brand-text">MODUL <span class="brand-badge ml-1">APP</span></span>
     </a>

     <!-- Sidebar -->
     <div class="sidebar">
         <!-- Sidebar user (optional) -->
         <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
             <div class="image mr-2">
                 <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold" style="width: 36px; height: 36px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                     @auth
                         {{ substr(auth()->user()->full_name, 0, 1) }}
                     @else
                         <i class="fas fa-user"></i>
                     @endauth
                 </div>
             </div>
             <div class="info">
                 @auth
                     <a href="/dashboard" class="d-block text-truncate" style="max-width: 150px;">{{ strtoupper(auth()->user()->full_name) }}</a>
                     <span class="user-role"><i class="fas fa-circle text-success mr-1" style="font-size: 0.45rem;"></i> Online</span>
                 @else
                     <a href="/login" class="d-block text-white-50">PENGUNJUNG</a>
                 @endauth
             </div>
         </div>
         <!-- Sidebar Menu -->
         <nav class="mt-2">
             <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                 data-accordion="false">
                <li class="nav-item">
                    <a href="/video" class="nav-link {{ ($active ?? '') === 'video' ? 'active' : '' }}">
                        <i class="nav-icon fas fa-play-circle text-danger"></i>
                        <p>Video Materi</p>
                    </a>
                </li>

                @auth
                <li class="nav-item">
                    <a href="/absent" class="nav-link {{ ($active ?? '') === 'absent' ? 'active' : '' }}">
                        <i class="nav-icon fas fa-calendar-check text-success"></i>
                        <p>Presence</p>
                    </a>
                </li>
                 <li class="nav-item">
                     <a href="/document" class="nav-link {{ ($active ?? '') === 'document' ? 'active' : '' }}">
                         <i class="nav-icon fas fa-file-alt text-warning"></i>
                         <p>Document</p>
                     </a>
                 </li>
                <li class="nav-item">
                     <a href="#" class="nav-link {{ ($active ?? '') === 'quiz' ? 'active' : '' }}">
                         <i class="nav-icon fas fa-award" style="color: #a855f7;"></i>
                         <p>
                             Quiz
                             <i class="right fas fa-angle-left"></i>
                         </p>
                     </a>
                     <ul class="nav nav-treeview">
                        <li class="nav-item">
                             <a href="/quiz" class="nav-link">
                                 <i class="fas fa-plus nav-icon text-xs"></i>
                                 <p>Add Quiz</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/question" class="nav-link">
                                 <i class="fas fa-question nav-icon text-xs"></i>
                                 <p>Question</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/quiz/history" class="nav-link">
                                 <i class="fas fa-poll nav-icon text-xs"></i>
                                 <p>History</p>
                             </a>
                         </li>
                     </ul>
                 </li>
                 <li class="nav-item">
                     <a href="#" class="nav-link {{ ($active ?? '') === 'setting' ? 'active' : '' }}">
                         <i class="nav-icon fas fa-sliders-h" style="color: #06b6d4;"></i>
                         <p>
                             Settings
                             <i class="right fas fa-angle-left"></i>
                         </p>
                     </a>
                     <ul class="nav nav-treeview">
                        <li class="nav-item">
                             <a href="/user" class="nav-link">
                                 <i class="fas fa-users-cog nav-icon text-xs"></i>
                                 <p>User</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/divisi" class="nav-link">
                                 <i class="fas fa-city nav-icon text-xs"></i>
                                 <p>Divisi</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/subdivisi" class="nav-link">
                                 <i class="fas fa-building nav-icon text-xs"></i>
                                 <p>Sub Divisi</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/joblevel" class="nav-link">
                                 <i class="fas fa-briefcase nav-icon text-xs"></i>
                                 <p>Job Level</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="/dokumentype" class="nav-link">
                                 <i class="fas fa-bookmark nav-icon text-xs"></i>
                                 <p>Document Type</p>
                             </a>
                         </li>
                     </ul>
                 </li>
                 @endauth
             </ul>
         </nav>
         <!-- /.sidebar-menu -->
     </div>
     <!-- /.sidebar -->

     <div class="sidebar-custom mb-3 px-3">
         @auth
             <form action="/logout" method="POST">
                 @csrf
                 <button type="submit" class="btn btn-outline-danger btn-sm btn-block" style="border-radius: 8px;">
                     <i class="fas fa-sign-out-alt mr-1"></i> Log Out
                 </button>
             </form>
         @else
             <a href="/login" class="btn btn-primary btn-sm btn-block text-white" style="border-radius: 8px;">
                 <i class="fas fa-sign-in-alt mr-1"></i> Login Admin
             </a>
         @endauth
     </div>
     <!-- /.sidebar-custom -->
 </aside>
