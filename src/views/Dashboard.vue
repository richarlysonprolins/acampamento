<template>
  <v-app>
    <!-- Drawer lateral -->
    <v-navigation-drawer v-model="drawer" :rail="rail" permanent @click="rail = false" class="">
      <v-list>
        <v-list-item
          :prepend-avatar="userData.avatar"
          :title="userData.full_name"
          :subtitle="getUserRoleText(userData.role)"
        >
          <template #append>
            <v-btn icon="mdi-chevron-left" variant="text" @click.stop="rail = !rail"></v-btn>
          </template>
        </v-list-item>
      </v-list>

      <v-divider></v-divider>

      <v-list density="compact" nav>
        <v-list-item prepend-icon="mdi-home-city" title="Home" @click="selected = 'home'" />
        <v-list-item prepend-icon="mdi-account" title="Minha Conta" @click="selected = 'account'" />
        <v-list-item
          prepend-icon="mdi-account-group-outline"
          title="Usuários"
          @click="selected = 'users'"
          :disabled="!userPermissions.can_manage_users"
        />
        <v-list-item
          prepend-icon="mdi-office-building"
          title="Unidades"
          @click="selected = 'unity'"
          :disabled="!userPermissions.can_manage_units"
        />
        <v-list-item
          prepend-icon="mdi-account-multiple"
          title="Membros"
          @click="selected = 'member'"
          :disabled="!userPermissions.can_manage_members"
        />
        <v-list-item
          prepend-icon="mdi-plus-circle-outline"
          title="Registrar Pontuação"
          @click="selected = 'registerpontuate'"
          :disabled="!userPermissions.can_register_scores"
        />
        <v-list-item
          prepend-icon="mdi-trophy"
          title="Ranking Final"
          @click="selected = 'ranking'"
          :disabled="!userPermissions.can_view_reports"
        />
        <v-list-item
          prepend-icon="mdi-gamepad-variant"
          title="Brincadeiras"
          @click="selected = 'games'"
          :disabled="!userPermissions.can_register_games"
        />
        <v-list-item
          prepend-icon="mdi-information"
          title="Créditos"
          @click="selected = 'credits'"
        />
      </v-list>
    </v-navigation-drawer>

    <!-- Conteúdo principal -->
    <v-main class="pa-6 bg-grey-lighten-4">
      <v-container fluid>
        <component :is="currentView" />
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted, watch, provide, inject } from 'vue'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  BarElement,
  BarController,
} from 'chart.js'

// Registrar TODOS os elementos necessários do Chart.js
ChartJS.register(Title, Tooltip, Legend, CategoryScale, LinearScale, BarElement, BarController)
// Logo após os imports, adicione:
const API_BASE_URL = import.meta.env.VITE_API_URL || '${API_BASE_URL}'

// E substitua TODAS as chamadas fetch, exemplo:
// ANTES: fetch('${API_BASE_URL}/users.php')
// DEPOIS: fetch(`${API_BASE_URL}/users.php`)
const drawer = ref(true)
const rail = ref(true)
const selected = ref('home')

// Dados do usuário - carregados UMA vez aqui
const userData = reactive({
  full_name: 'Usuário',
  username: 'user',
  email: 'user@example.com',
  role: 'viewer',
  avatar: 'https://randomuser.me${API_BASE_URL}/portraits/men/85.jpg',
})

const userPermissions = reactive({
  can_manage_users: false,
  can_manage_units: false,
  can_manage_members: false,
  can_register_scores: false,
  can_view_reports: false,
  can_register_games: false,
})

// 👇 PROVIDE - Disponibiliza para todos os componentes filhos
provide('userData', userData)
provide('userPermissions', userPermissions)

// Carregar dados do usuário do localStorage
onMounted(() => {
  const savedUser = localStorage.getItem('user')
  if (savedUser) {
    const user = JSON.parse(savedUser)

    // Atualiza os dados reativos
    Object.assign(userData, user)

    // Atualiza as permissões
    if (user.permissions) {
      Object.assign(userPermissions, user.permissions)
    }
  }
})

// Função auxiliar para traduzir o role
const getUserRoleText = (role) => {
  const roles = {
    admin: 'Administrador',
    secretary: 'Secretário',
    viewer: 'Visualizador',
  }
  return roles[role] || 'Usuário'
}

// Componente BarChart simplificado usando a Composition API
const BarChart = {
  template: `
    <div>
      <canvas style="display: block; box-sizing: border-box; height: 325px; width: 925px;" ref="chartCanvas"></canvas>
    </div>
  `,
  props: {
    chartData: {
      type: Object,
      required: true,
      default: () => ({
        labels: [],
        datasets: [],
      }),
    },
    chartOptions: {
      type: Object,
      default: () => ({}),
    },
  },
  setup(props) {
    const chartCanvas = ref(null)
    let chartInstance = null

    const renderChart = () => {
      if (chartInstance) {
        chartInstance.destroy()
      }

      if (
        chartCanvas.value &&
        props.chartData &&
        props.chartData.labels &&
        props.chartData.labels.length > 0
      ) {
        try {
          chartInstance = new ChartJS(chartCanvas.value, {
            type: 'bar',
            data: props.chartData,
            options: {
              responsive: true,
              maintainAspectRatio: false,
              ...props.chartOptions,
            },
          })
        } catch (error) {
          console.error('Erro ao renderizar gráfico:', error)
        }
      }
    }

    onMounted(() => {
      renderChart()
    })

    watch(
      () => props.chartData,
      () => {
        renderChart()
      },
      { deep: true },
    )

    watch(
      () => props.chartOptions,
      () => {
        if (chartInstance) {
          chartInstance.options = {
            ...chartInstance.options,
            ...props.chartOptions,
          }
          chartInstance.update()
        }
      },
      { deep: true },
    )

    return {
      chartCanvas,
    }
  },
}

const SpinnerGrow = {
  template: `
    <div class="spinner-grow" :style="{ width: size, height: size }" role="status">
      <span class="visually-hidden">Carregando...</span>
    </div>
  `,
  props: {
    size: {
      type: String,
      default: '1rem',
    },
  },
}

// Telas simuladas
const HomeView = {
  template: `
    <v-container>
      <v-row class="mb-6">
        <v-col cols="12">
          <v-card class="pa-6 rounded-lg" elevation="2">
            <v-row align="center">
              <v-col cols="auto">
                <v-avatar size="80" class="mr-4">
                  <v-img :src="userData.avatar" :alt="userData.full_name" />
                </v-avatar>
              </v-col>
              <v-col>
                <h1 class="text-h4 font-weight-bold">Olá, {{ userData.full_name }}! 👋</h1>
                <p class="text-body-1 text-grey">Bem-vindo(a) ao Sistema de Pontuação de Desbravadores</p>
                <v-chip :color="getRoleColor(userData.role)" class="mt-2">
                  <v-icon start>mdi-account</v-icon>
                  {{ getUserRoleText(userData.role) }}
                </v-chip>
              </v-col>
            </v-row>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <!-- Card de Estatísticas REAIS -->
        <v-col cols="12" md="3">
          <v-card class="pa-4 text-center rounded-lg" color="primary" elevation="2">
            <v-icon size="48" color="white" class="mb-2">mdi-office-building</v-icon>
            <h3 class="text-h5 font-weight-bold white--text">{{ dashboardData.stats.totalUnits }}</h3>
            <p class="white--text">Unidades</p>
          </v-card>
        </v-col>

        <v-col cols="12" md="3">
          <v-card class="pa-4 text-center rounded-lg" color="secondary" elevation="2">
            <v-icon size="48" color="white" class="mb-2">mdi-account</v-icon>
            <h3 class="text-h5 font-weight-bold white--text">{{ dashboardData.stats.totalMembers }}</h3>
            <p class="white--text">Membros</p>
          </v-card>
        </v-col>

        <v-col cols="12" md="3">
          <v-card class="pa-4 text-center rounded-lg" color="success" elevation="2">
            <v-icon size="48" color="white" class="mb-2">mdi-star</v-icon>
            <h3 class="text-h5 font-weight-bold white--text">{{ dashboardData.stats.totalPoints }}</h3>
            <p class="white--text">Pontos Hoje</p>
          </v-card>
        </v-col>

        <v-col cols="12" md="3">
          <v-card class="pa-4 text-center rounded-lg" color="warning" elevation="2">
            <v-icon size="48" color="white" class="mb-2">mdi-trophy</v-icon>
            <h3 class="text-h5 font-weight-bold white--text">{{ userRankingPosition }}</h3>
            <p class="white--text">Líder no Ranking</p>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mt-6">
        <v-col cols="12" md="8">
          <v-card class="pa-4 rounded-lg" elevation="2">
            <h3 class="text-h6 mb-4">📊 Atividade Recente</h3>
            <v-list lines="two" v-if="dashboardData.recentActivities.length">
              <v-list-item
                v-for="activity in dashboardData.recentActivities"
                :key="activity.id"
                :prepend-avatar="activity.avatar"
              >
                <template #title>
                  <strong>{{ activity.user }}</strong> {{ activity.action }}
                </template>
                <template #subtitle>
                  {{ activity.time }}
                </template>
                <template #append>
                  <v-chip size="small" color="success">
                    +{{ activity.points }} pts
                  </v-chip>
                </template>
              </v-list-item>
            </v-list>
            <div v-else class="text-center py-4">
              <v-icon size="48" color="grey" class="mb-2">mdi-information</v-icon>
              <p class="text-grey">Nenhuma atividade recente</p>
            </div>
          </v-card>
        </v-col>

        <v-col cols="12" md="4">
          <v-card class="pa-4 rounded-lg" elevation="2">
            <h3 class="text-h6 mb-4">🏆 Top Unidades</h3>
            <v-list>
              <v-list-item
                v-for="unit in topUnits"
                :key="unit.name"
                :prepend-avatar="getUnitAvatar(unit.name)"
              >
                <template #title>
                  <strong>{{ unit.position }}º</strong> {{ unit.name }}
                </template>
                <template #subtitle>
                  {{ unit.points }} pontos
                </template>
              </v-list-item>
            </v-list>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  `,
  setup() {
    const userData = ref({
      full_name: 'Usuário',
      username: 'user',
      email: 'user@example.com',
      role: 'viewer',
      avatar: 'https://randomuser.me${API_BASE_URL}/portraits/men/85.jpg',
    })

    const dashboardData = ref({
      stats: {
        totalUnits: 0,
        totalMembers: 0,
        totalPoints: 0,
        ranking: [],
      },
      recentActivities: [],
    })

    const loading = ref(true)
    const baseUrl = ref('')

    // Computed properties
    const topUnits = computed(() => {
      return dashboardData.value.stats.ranking.slice(0, 5)
    })

    const userRankingPosition = computed(() => {
      return dashboardData.value.stats.ranking.length > 0
        ? dashboardData.value.stats.ranking[0].name // Agora pega o nome da unidade líder
        : 'Nenhuma'
    })

    // Carregar dados do usuário e do dashboard
    onMounted(async () => {
      // Carregar dados do usuário do localStorage
      const savedUser = localStorage.getItem('user')
      if (savedUser) {
        const user = JSON.parse(savedUser)
        userData.value = {
          ...userData.value,
          ...user,
        }
      }

      // Determinar base URL
      baseUrl.value = window.location.origin

      // Carregar dados do dashboard da API
      await loadDashboardData()
    })

    const loadDashboardData = async () => {
      try {
        loading.value = true
        const response = await fetch('${API_BASE_URL}/dashboard_data.php')

        if (!response.ok) {
          throw new Error('Erro ao carregar dados')
        }

        const result = await response.json()

        if (result.success) {
          dashboardData.value = result.data
        } else {
          console.error('Erro na API:', result.message)
        }
      } catch (error) {
        console.error('Erro ao carregar dashboard:', error)
      } finally {
        loading.value = false
      }
    }

    // Funções auxiliares
    const getRoleColor = (role) => {
      const colors = {
        admin: 'red',
        secretary: 'blue',
        viewer: 'green',
      }
      return colors[role] || 'grey'
    }

    const getUserRoleText = (role) => {
      const roles = {
        admin: 'Administrador',
        secretary: 'Secretário',
        viewer: 'Visualizador',
      }
      return roles[role] || 'Usuário'
    }

    const getUnitAvatar = (unitName) => {
      // Encontra a unidade nos dados do dashboard para pegar o avatar real
      const unit = dashboardData.value.stats.ranking.find((u) => u.name === unitName)
      if (unit && unit.avatar) {
        return unit.avatar
      }
      // Fallback para avatar padrão se não encontrar
      return `${baseUrl.value}/assets/images/units/default.png`
    }

    return {
      userData,
      dashboardData,
      loading,
      topUnits,
      userRankingPosition,
      getRoleColor,
      getUserRoleText,
      getUnitAvatar,
      SpinnerGrow,
    }
  },
}

const AccountView = {
  template: `
    <v-container>
      <v-row class="mb-6">
        <v-col cols="12">
          <v-card class="pa-6 rounded-lg" elevation="2">
            <div class="text-center mb-6">
              <v-avatar size="120" class="mb-4">
                <v-img :src="getAvatarUrl(userForm.avatar)" alt="Avatar" />
                <v-btn
                  icon
                  size="small"
                  color="primary"
                  class="avatar-edit-btn"
                  @click="$refs.avatarInput.click()"
                >
                  <v-icon>mdi-camera</v-icon>
                </v-btn>
              </v-avatar>
              <input
                ref="avatarInput"
                type="file"
                accept="image/*"
                style="display: none"
                @change="handleAvatarUpload"
              />
              <div class="text-caption text-grey">Clique no ícone para alterar a foto</div>
            </div>

            <v-form @submit.prevent="updateAccount">
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="userForm.full_name"
                    label="Nome Completo"
                    prepend-inner-icon="mdi-account"
                    :rules="[rules.required]"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="userForm.email"
                    label="E-mail"
                    prepend-inner-icon="mdi-email"
                    :rules="[rules.required, rules.email]"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="userForm.username"
                    label="Usuário"
                    prepend-inner-icon="mdi-account"
                    :rules="[rules.required]"
                    :disabled="!canEditUsername"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="userForm.phone"
                    label="Telefone"
                    prepend-inner-icon="mdi-phone"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="userRoleText"
                    label="Cargo"
                    prepend-inner-icon="mdi-briefcase"
                    disabled
                  ></v-text-field>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    label="Criado em"
                    :value="createdAtFormatted"
                    prepend-inner-icon="mdi-calendar"
                    disabled
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="userForm.bio"
                    label="Biografia"
                    prepend-inner-icon="mdi-text"
                    rows="3"
                    counter
                    maxlength="500"
                    placeholder="Conte um pouco sobre você..."
                  ></v-textarea>
                </v-col>
                <v-col cols="12">
                  <v-btn type="submit" color="primary" size="large" :loading="loading">
                    Salvar Alterações
                  </v-btn>
                  <v-btn variant="text" class="ml-2" @click="resetForm" :disabled="loading">
                    Cancelar
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="6">
          <v-card class="pa-6 rounded-lg" elevation="2">
            <h3 class="text-h6 mb-4">🔑 Alterar Senha</h3>
            <v-form @submit.prevent="changePassword">
              <v-row>
                <v-col cols="12">
                  <v-text-field
                    v-model="passwordData.current"
                    label="Senha Atual"
                    type="password"
                    prepend-inner-icon="mdi-lock"
                    :rules="[rules.required]"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="passwordData.new"
                    label="Nova Senha"
                    type="password"
                    prepend-inner-icon="mdi-lock-plus"
                    :rules="[rules.required, rules.minLength]"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="passwordData.confirm"
                    label="Confirmar Nova Senha"
                    type="password"
                    prepend-inner-icon="mdi-lock-check"
                    :rules="[rules.required, passwordMatch]"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-btn type="submit" color="primary" :loading="passwordLoading">
                    Alterar Senha
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card class="pa-6 rounded-lg" elevation="2">
            <h3 class="text-h6 mb-4">📊 Estatísticas do Usuário</h3>
            <v-list>
              <v-list-item>
                <template #prepend>
                  <v-avatar color="primary" size="40">
                    <v-icon icon="mdi-star" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title>Pontuações Registradas</v-list-item-title>
                <v-list-item-subtitle>{{ userStats.scoresRegistered }} registros</v-list-item-subtitle>
              </v-list-item>

              <v-list-item>
                <template #prepend>
                  <v-avatar color="secondary" size="40">
                    <v-icon icon="mdi-login" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title>Último Login</v-list-item-title>
                <v-list-item-subtitle>{{ formatDate(userStats.lastLogin) }}</v-list-item-subtitle>
              </v-list-item>

              <v-list-item>
                <template #prepend>
                  <v-avatar color="success" size="40">
                    <v-icon icon="mdi-account" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title>Status da Conta</v-list-item-title>
                <v-list-item-subtitle>
                  <v-chip size="small" :color="userForm.is_active ? 'success' : 'error'">
                    {{ userForm.is_active ? 'Ativa' : 'Inativa' }}
                  </v-chip>
                </v-list-item-subtitle>
              </v-list-item>

              <v-list-item>
                <template #prepend>
                  <v-avatar color="warning" size="40">
                    <v-icon icon="mdi-shield-account" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title>Permissões</v-list-item-title>
                <v-list-item-subtitle>
                  <v-chip size="small" color="info">
                    {{ userRoleText }}
                  </v-chip>
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  `,

  setup() {
    const userData = inject('userData')
    const userPermissions = inject('userPermissions')

    const loading = ref(false)
    const passwordLoading = ref(false)
    const uploading = ref(false)

    const userForm = reactive({
      id: '',
      full_name: '',
      email: '',
      username: '',
      phone: '',
      bio: '',
      avatar: '',
      role: '',
      created_at: '',
      is_active: true,
    })

    const passwordData = reactive({
      current: '',
      new: '',
      confirm: '',
    })

    const userStats = reactive({
      scoresRegistered: 0,
      lastLogin: null,
    })

    const userRoleText = computed(() => {
      const roles = {
        admin: 'Administrador',
        secretary: 'Secretário',
        viewer: 'Visualizador',
      }
      return roles[userForm.role] || 'Usuário'
    })

    const rules = {
      required: (value) => !!value || 'Campo obrigatório.',
      email: (value) => /.+@.+\..+/.test(value) || 'E-mail inválido.',
      minLength: (value) => value.length >= 6 || 'Mínimo 6 caracteres.',
    }

    const canEditUsername = computed(() => {
      return userData.role === 'admin' || userData.role === 'secretary'
    })

    const passwordMatch = () => {
      return passwordData.new === passwordData.confirm || 'Senhas não conferem'
    }

    const getAvatarUrl = (avatar) => {
      if (!avatar) return 'https://i.pravatar.cc/150'
      if (avatar.startsWith('http')) return avatar
      if (avatar.startsWith('src/assets/')) return '/' + avatar
      return avatar
    }

    const loadUserData = async () => {
      try {
        const response = await fetch('${API_BASE_URL}/user_profile.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            Object.assign(userForm, result.data.user)
            Object.assign(userStats, result.data.stats)
            Object.assign(userData, result.data.user)
          }
        }
      } catch (error) {
        console.error('Erro ao carregar dados do usuário:', error)
        // Fallback para dados do localStorage
        const savedUser = localStorage.getItem('user')
        if (savedUser) {
          const user = JSON.parse(savedUser)
          Object.assign(userForm, user)
        }
      }
    }

    const handleAvatarUpload = async (event) => {
      const file = event.target.files[0]
      if (!file) return

      uploading.value = true
      try {
        const formData = new FormData()
        formData.append('avatar', file)
        formData.append('type', 'user')

        const response = await fetch('${API_BASE_URL}/upload.php', {
          method: 'POST',
          body: formData,
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            userForm.avatar = result.filename
            userData.avatar = result.url

            // Atualizar imediatamente no banco
            await updateUserAvatar(result.filename)
          }
        }
      } catch (error) {
        console.error('Erro ao fazer upload:', error)
        alert('Erro ao fazer upload da imagem')
      } finally {
        uploading.value = false
        event.target.value = '' // Reset input
      }
    }

    const updateUserAvatar = async (filename) => {
      try {
        const response = await fetch('${API_BASE_URL}/user_profile.php?action=update_avatar', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ avatar: filename }),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            console.log('Avatar atualizado com sucesso')
          }
        }
      } catch (error) {
        console.error('Erro ao atualizar avatar:', error)
      }
    }

    const createdAtFormatted = computed(() => formatDate(userForm.created_at))

    const updateAccount = async () => {
      try {
        loading.value = true

        const response = await fetch('${API_BASE_URL}/user_profile.php?action=update', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(userForm),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            // Atualizar localStorage
            const savedUser = localStorage.getItem('user')
            if (savedUser) {
              const user = JSON.parse(savedUser)
              Object.assign(user, userForm)
              localStorage.setItem('user', JSON.stringify(user))
            }

            // Atualizar userData global
            Object.assign(userData, userForm)

            alert('Dados atualizados com sucesso!')
          } else {
            alert(result.message || 'Erro ao atualizar dados')
          }
        }
      } catch (error) {
        console.error('Erro ao atualizar conta:', error)
        alert('Erro ao atualizar dados')
      } finally {
        loading.value = false
      }
    }

    const changePassword = async () => {
      if (passwordData.new !== passwordData.confirm) {
        alert('As senhas não conferem')
        return
      }

      try {
        passwordLoading.value = true

        const response = await fetch('${API_BASE_URL}/user_profile.php?action=change_password', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(passwordData),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            alert('Senha alterada com sucesso!')
            Object.assign(passwordData, { current: '', new: '', confirm: '' })
          } else {
            alert(result.message || 'Erro ao alterar senha')
          }
        }
      } catch (error) {
        console.error('Erro ao alterar senha:', error)
        alert('Erro ao alterar senha')
      } finally {
        passwordLoading.value = false
      }
    }

    const resetForm = () => {
      loadUserData()
      Object.assign(passwordData, { current: '', new: '', confirm: '' })
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Nunca'
      return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    }

    onMounted(() => {
      loadUserData()
    })

    return {
      userData,
      userPermissions,
      userForm,
      passwordData,
      userStats,
      loading,
      passwordLoading,
      uploading,
      userRoleText,
      rules,
      canEditUsername,
      passwordMatch,
      getAvatarUrl,
      handleAvatarUpload,
      updateAccount,
      changePassword,
      resetForm,
      formatDate,
      createdAtFormatted,
    }
  },
}

const UsersView = {
  template: `
    <v-container>
      <v-row class="mb-4">
        <v-col cols="12">
          <h2 class="text-h5 mb-2">👥 Usuários do Sistema</h2>
          <p class="text-body-2 mb-4">Gerencie os usuários com acesso ao sistema</p>
        </v-col>
      </v-row>

      <!-- Lista de Usuários -->
      <v-row>
        <v-col cols="12">
          <v-card class="rounded-lg" elevation="2">
            <v-list lines="two" v-if="users.length > 0">
              <v-list-item
                v-for="user in users"
                :key="user.id"
              >
                <template #prepend>
                  <v-avatar size="48" rounded="circle">
                    <v-img :src="getAvatarUrl(user.avatar)" :alt="user.full_name" />
                  </v-avatar>
                </template>

                <v-list-item-title class="font-weight-medium">
                  {{ user.full_name }}
                </v-list-item-title>

                <v-list-item-subtitle>
                  {{ user.email }} • {{ getUserRoleText(user.role) }}
                  <v-chip size="x-small" :color="user.is_active ? 'success' : 'error'" class="ml-1">
                    {{ user.is_active ? 'Ativo' : 'Inativo' }}
                  </v-chip>
                </v-list-item-subtitle>

                <template #append>
                  <v-btn
                    icon
                    variant="text"
                    @click="editUser(user)"
                    :disabled="!userPermissions.can_manage_users"
                  >
                    <v-icon icon="mdi-pencil" />
                  </v-btn>
                </template>
              </v-list-item>
            </v-list>

            <!-- Mensagem quando não há usuários -->
            <div v-else class="text-center pa-6">
              <v-icon size="64" color="grey" class="mb-3">mdi-account-multiple-off</v-icon>
              <h3 class="text-h6">Nenhum usuário encontrado</h3>
              <p class="text-grey">Clique no botão + para adicionar o primeiro usuário</p>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Botão Flutuante -->
      <v-btn
        color="primary"
        icon="mdi-plus"
        size="large"
        class="position-fixed"
        style="bottom: 24px; right: 24px;"
        @click="openCreateDialog()"
        :disabled="!userPermissions.can_manage_users"
      ></v-btn>

      <!-- Modal de Criação/Edição -->
      <v-dialog v-model="dialog" max-width="500">
        <v-card>
          <v-card-title>{{ editingUser ? 'Editar' : 'Criar' }} usuário</v-card-title>
          <v-card-text>
            <v-text-field
              label="Nome Completo"
              v-model="userForm.full_name"
              prepend-inner-icon="mdi-account"
              :rules="[rules.required]"
            />
            <v-text-field
              label="E-mail"
              v-model="userForm.email"
              prepend-inner-icon="mdi-email"
              :rules="[rules.required, rules.email]"
            />
            <v-text-field
              label="Usuário"
              v-model="userForm.username"
              prepend-inner-icon="mdi-account"
              :rules="[rules.required]"
            />
            <v-select
              label="Cargo"
              v-model="userForm.role"
              prepend-inner-icon="mdi-briefcase"
              :items="roles"
              :rules="[rules.required]"
            />

            <!-- Upload de Avatar -->
            <div class="mb-4">
              <label class="text-caption text-grey">Avatar</label>
              <div class="d-flex align-center mt-2">
                <v-avatar size="64" class="mr-4">
                  <v-img :src="getPreviewAvatar()" alt="Preview" />
                </v-avatar>
                <v-file-input
                  v-model="avatarFile"
                  accept="image/*"
                  label="Selecionar imagem"
                  prepend-icon="mdi-camera"
                  density="compact"
                  @update:model-value="handleAvatarUpload"
                  :loading="uploading"
                />
              </div>
            </div>

            <v-text-field
              v-if="!editingUser"
              label="Senha"
              v-model="userForm.password"
              type="password"
              prepend-inner-icon="mdi-lock"
              :rules="[rules.required]"
            />
            <v-switch
              v-model="userForm.is_active"
              label="Usuário ativo"
              color="success"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDialog">Cancelar</v-btn>
            <v-btn color="primary" @click="saveUser" :loading="saving">Salvar</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  `,

  setup() {
    const userPermissions = inject('userPermissions')
    const users = ref([])
    const dialog = ref(false)
    const editingUser = ref(null)
    const avatarFile = ref(null)
    const uploading = ref(false)
    const saving = ref(false)

    const userForm = reactive({
      full_name: '',
      email: '',
      username: '',
      role: 'viewer',
      avatar: '',
      password: '',
      is_active: true,
      created_at: '',
    })

    const roles = [
      { title: 'Administrador', value: 'admin' },
      { title: 'Secretário', value: 'secretary' },
      { title: 'Visualizador', value: 'viewer' },
    ]

    const rules = {
      required: (value) => !!value || 'Campo obrigatório.',
      email: (value) => /.+@.+\..+/.test(value) || 'E-mail inválido.',
    }

    const getAvatarUrl = (avatar) => {
      if (!avatar) return '../src/assets/images/users/default.png://i.pravatar.cc/100'
      if (avatar.startsWith('http')) return avatar
      // Agora usa o caminho relativo ../assets/images/
      return avatar.startsWith('../') ? avatar : `../assets/images/units/${avatar}`
    }

    const getPreviewAvatar = () => {
      if (avatarFile.value) {
        return URL.createObjectURL(avatarFile.value)
      }
      return userForm.avatar
        ? getAvatarUrl(userForm.avatar)
        : '../src/assets/images/users/default.png'
    }

    const handleAvatarUpload = async (file) => {
      if (!file) return

      uploading.value = true
      try {
        const formData = new FormData()
        formData.append('avatar', file)
        formData.append('type', 'user')

        const response = await fetch('${API_BASE_URL}/upload.php', {
          method: 'POST',
          body: formData,
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            userForm.avatar = result.filename
          }
        }
      } catch (error) {
        console.error('Erro ao fazer upload:', error)
      } finally {
        uploading.value = false
      }
    }

    const loadUsers = async () => {
      try {
        const response = await fetch('${API_BASE_URL}/users.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            users.value = result.data
          }
        }
      } catch (error) {
        console.error('Erro ao carregar usuários:', error)
      }
    }

    const openCreateDialog = () => {
      editingUser.value = null
      avatarFile.value = null
      Object.assign(userForm, {
        full_name: '',
        email: '',
        username: '',
        role: 'viewer',
        avatar: '',
        password: '',
        is_active: true,
        created_at: '',
      })
      dialog.value = true
    }

    const editUser = (user) => {
      editingUser.value = user
      avatarFile.value = null
      Object.assign(userForm, { ...user, password: '' })
      dialog.value = true
    }

    const saveUser = async () => {
      if (!userForm.full_name || !userForm.email || !userForm.username || !userForm.role) {
        return
      }

      saving.value = true
      try {
        const url = editingUser.value
          ? '${API_BASE_URL}/users.php?action=update'
          : '${API_BASE_URL}/users.php?action=create'
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(userForm),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            await loadUsers()
            closeDialog()
          }
        }
      } catch (error) {
        console.error('Erro ao salvar usuário:', error)
      } finally {
        saving.value = false
      }
    }

    const closeDialog = () => {
      dialog.value = false
      editingUser.value = null
      avatarFile.value = null
    }

    onMounted(() => {
      loadUsers()
    })

    // Adicionar esta função dentro do setup do UsersView, antes do return
    const getUserRoleText = (role) => {
      const roles = {
        admin: 'Administrador',
        secretary: 'Secretário',
        viewer: 'Visualizador',
      }
      return roles[role] || 'Usuário'
    }

    return {
      users,
      userPermissions,
      dialog,
      editingUser,
      userForm,
      avatarFile,
      uploading,
      saving,
      roles,
      rules,
      getAvatarUrl,
      getUserRoleText,
      getPreviewAvatar,
      handleAvatarUpload,
      openCreateDialog,
      editUser,
      saveUser,
      closeDialog,
    }
  },
}

const UnityView = {
  template: `
    <v-container>
      <v-row class="mb-4">
        <v-col cols="12">
          <h2 class="text-h5 mb-2">🏢 Unidades</h2>
          <p class="text-body-2 mb-4">Gerencie as unidades dos desbravadores</p>
        </v-col>
      </v-row>

      <!-- Lista de Unidades -->
      <v-row>
        <v-col cols="12">
          <v-card class="rounded-lg" elevation="2">
            <v-list lines="two" v-if="units.length > 0">
              <v-list-item
                v-for="unit in units"
                :key="unit.id"
              >
                <template #prepend>
                  <v-avatar size="48" rounded="circle" :color="unit.color || 'primary'">
                    <v-img v-if="unit.avatar" :src="getAvatarUrl(unit.avatar)" alt="Avatar" />
                    <v-icon v-else icon="mdi-office-building" color="white" />
                  </v-avatar>
                </template>

                <v-list-item-title class="font-weight-medium">
                  {{ unit.name }}
                </v-list-item-title>

                <v-list-item-subtitle>
                  {{ unit.club_name }} • {{ unit.member_count }} membros
                </v-list-item-subtitle>

                <template #append>
                  <v-btn
                    icon
                    variant="text"
                    @click="editUnity(unit)"
                    :disabled="!userPermissions.can_manage_units"
                  >
                    <v-icon icon="mdi-pencil" />
                  </v-btn>
                </template>
              </v-list-item>
            </v-list>

            <!-- Mensagem quando não há unidades -->
            <div v-else class="text-center pa-6">
              <v-icon size="64" color="grey" class="mb-3">mdi-office-building-off</v-icon>
              <h3 class="text-h6">Nenhuma unidade encontrada</h3>
              <p class="text-grey">Clique no botão + para adicionar a primeira unidade</p>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Botão Flutuante -->
      <v-btn
        color="primary"
        icon="mdi-plus"
        size="large"
        class="position-fixed"
        style="bottom: 24px; right: 24px;"
        @click="openCreateDialog()"
        :disabled="!userPermissions.can_manage_units"
      ></v-btn>

      <!-- Modal de Criação/Edição -->
      <v-dialog v-model="dialog" max-width="500">
        <v-card>
          <v-card-title>{{ editingUnity ? 'Editar' : 'Criar' }} unidade</v-card-title>
          <v-card-text>
            <v-text-field
              label="Nome da Unidade"
              v-model="unityForm.name"
              prepend-inner-icon="mdi-office-building"
              :rules="[rules.required, rules.counter]"
              maxlength="20"
              counter
            />
            <v-select
              label="Clube"
              v-model="unityForm.club_id"
              prepend-inner-icon="mdi-account-group"
              :items="availableClubs"
              item-title="name"
              item-value="id"
              :rules="[rules.required]"
            />
            <v-text-field
              label="Cor da Unidade"
              v-model="unityForm.color"
              type="color"
              prepend-inner-icon="mdi-palette"
            />

            <!-- Upload de Avatar -->
            <div class="mb-4">
              <label class="text-caption text-grey">Avatar da Unidade</label>
              <div class="d-flex align-center mt-2">
                <v-avatar size="64" :color="unityForm.color || 'primary'" class="mr-4">
                  <v-img v-if="getPreviewAvatar()" :src="getPreviewAvatar()" alt="Preview" />
                  <v-icon v-else icon="mdi-office-building" color="white" />
                </v-avatar>
                <v-file-input
                  v-model="avatarFile"
                  accept="image/*"
                  label="Selecionar imagem"
                  prepend-icon="mdi-camera"
                  density="compact"
                  @update:model-value="handleAvatarUpload"
                  :loading="uploading"
                />
              </div>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDialog">Cancelar</v-btn>
            <v-btn color="primary" @click="saveUnity" :loading="saving">Salvar</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  `,

  setup() {
    const userPermissions = inject('userPermissions')
    const units = ref([])
    const availableClubs = ref([])
    const dialog = ref(false)
    const editingUnity = ref(null)
    const avatarFile = ref(null)
    const uploading = ref(false)
    const saving = ref(false)

    const unityForm = reactive({
      name: '',
      club_id: '',
      color: '#2196F3',
      avatar: '',
    })

    const rules = {
      required: (value) => !!value || 'Campo obrigatório.',
      counter: (value) => value.length <= 20 || 'Máximo 20 caracteres',
    }

    const getAvatarUrl = (avatar) => {
      if (!avatar) return null
      if (avatar.startsWith('http')) return avatar
      return `/uploads/units/${avatar}`
    }

    const getPreviewAvatar = () => {
      if (avatarFile.value) {
        return URL.createObjectURL(avatarFile.value)
      }
      return unityForm.avatar ? getAvatarUrl(unityForm.avatar) : null
    }

    const handleAvatarUpload = async (file) => {
      if (!file) return

      uploading.value = true
      try {
        const formData = new FormData()
        formData.append('avatar', file)
        formData.append('type', 'unit')

        const response = await fetch('${API_BASE_URL}/upload.php', {
          method: 'POST',
          body: formData,
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            unityForm.avatar = result.filename
          }
        }
      } catch (error) {
        console.error('Erro ao fazer upload:', error)
      } finally {
        uploading.value = false
      }
    }

    const loadUnits = async () => {
      try {
        const response = await fetch('${API_BASE_URL}/units.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            units.value = result.data
          }
        }
      } catch (error) {
        console.error('Erro ao carregar unidades:', error)
      }
    }

    const loadClubs = async () => {
      try {
        const response = await fetch('${API_BASE_URL}/clubs.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            availableClubs.value = result.data
          }
        }
      } catch (error) {
        console.error('Erro ao carregar clubes:', error)
      }
    }

    const openCreateDialog = () => {
      editingUnity.value = null
      avatarFile.value = null
      Object.assign(unityForm, {
        name: '',
        club_id: '',
        color: '#2196F3',
        avatar: '',
      })
      dialog.value = true
    }

    const editUnity = (unit) => {
      editingUnity.value = unit
      avatarFile.value = null
      Object.assign(unityForm, {
        ...unit,
        club_id: unit.club_id || '',
      })
      dialog.value = true
    }

    const saveUnity = async () => {
      if (!unityForm.name || !unityForm.club_id) {
        return
      }

      saving.value = true
      try {
        const url = editingUnity.value
          ? '${API_BASE_URL}/units.php?action=update'
          : '${API_BASE_URL}units.php?action=create'
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(unityForm),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            await loadUnits()
            closeDialog()
          }
        }
      } catch (error) {
        console.error('Erro ao salvar unidade:', error)
      } finally {
        saving.value = false
      }
    }

    const closeDialog = () => {
      dialog.value = false
      editingUnity.value = null
      avatarFile.value = null
    }

    onMounted(() => {
      loadUnits()
      loadClubs()
    })

    return {
      units,
      userPermissions,
      dialog,
      editingUnity,
      unityForm,
      avatarFile,
      uploading,
      saving,
      availableClubs,
      rules,
      getAvatarUrl,
      getPreviewAvatar,
      handleAvatarUpload,
      openCreateDialog,
      editUnity,
      saveUnity,
      closeDialog,
    }
  },
}

const MembersView = {
  template: `
    <v-container>
      <v-row class="mb-4">
        <v-col cols="12">
          <h2 class="text-h5 mb-2">👥 Membros</h2>
          <p class="text-body-2 mb-4">Gerencie os membros das unidades</p>
        </v-col>
      </v-row>

      <!-- Lista de Membros -->
      <v-row>
        <v-col cols="12">
          <v-card class="rounded-lg" elevation="2">
            <v-list lines="two" v-if="members.length > 0">
              <v-list-item
                v-for="member in members"
                :key="member.id"
              >
                <template #prepend>
                  <v-avatar size="48" rounded="circle">
                    <v-img :src="getAvatarUrl(member.avatar)" :alt="member.name" />
                  </v-avatar>
                </template>

                <v-list-item-title class="font-weight-medium">
                  {{ member.name }}
                </v-list-item-title>

                <v-list-item-subtitle>
                  {{ member.role_name }} • {{ member.unit_name }}
                  <v-chip size="x-small" :color="member.is_active ? 'success' : 'error'" class="ml-1">
                    {{ member.is_active ? 'Ativo' : 'Inativo' }}
                  </v-chip>
                </v-list-item-subtitle>

                <template #append>
                  <v-btn
                    icon
                    variant="text"
                    @click="editMember(member)"
                    :disabled="!userPermissions.can_manage_members"
                  >
                    <v-icon icon="mdi-pencil" />
                  </v-btn>
                </template>
              </v-list-item>
            </v-list>

            <!-- Mensagem quando não há membros -->
            <div v-else class="text-center pa-6">
              <v-icon size="64" color="grey" class="mb-3">mdi-account-off</v-icon>
              <h3 class="text-h6">Nenhum membro encontrado</h3>
              <p class="text-grey">Clique no botão + para adicionar o primeiro membro</p>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Botão Flutuante -->
      <v-btn
        color="primary"
        icon="mdi-plus"
        size="large"
        class="position-fixed"
        style="bottom: 24px; right: 24px;"
        @click="openCreateDialog()"
        :disabled="!userPermissions.can_manage_members"
      ></v-btn>

      <!-- Modal de Criação/Edição -->
      <v-dialog v-model="dialog" max-width="500">
        <v-card>
          <v-card-title>{{ editingMember ? 'Editar' : 'Criar' }} membro</v-card-title>
          <v-card-text>
            <v-text-field
              label="Nome do Membro"
              v-model="memberForm.name"
              prepend-inner-icon="mdi-account"
              :rules="[rules.required]"
            />
            <v-select
              label="Cargo"
              v-model="memberForm.role_id"
              prepend-inner-icon="mdi-briefcase"
              :items="availableRoles"
              item-title="name"
              item-value="id"
              :rules="[rules.required]"
            />
            <v-select
              label="Unidade"
              v-model="memberForm.unit_id"
              prepend-inner-icon="mdi-office-building"
              :items="availableUnits"
              item-title="name"
              item-value="id"
              :rules="[rules.required]"
            />

            <!-- Upload de Avatar -->
            <div class="mb-4">
              <label class="text-caption text-grey">Foto do Membro</label>
              <div class="d-flex align-center mt-2">
                <v-avatar size="64" class="mr-4">
                  <v-img :src="getPreviewAvatar()" alt="Preview" />
                </v-avatar>
                <v-file-input
                  v-model="avatarFile"
                  accept="image/*"
                  label="Selecionar imagem"
                  prepend-icon="mdi-camera"
                  density="compact"
                  @update:model-value="handleAvatarUpload"
                  :loading="uploading"
                />
              </div>
            </div>

            <v-text-field
              label="Data de Nascimento"
              v-model="memberForm.birth_date"
              type="date"
              prepend-inner-icon="mdi-cake"
            />
            <v-switch
              v-model="memberForm.is_active"
              label="Membro ativo"
              color="success"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDialog">Cancelar</v-btn>
            <v-btn color="primary" @click="saveMember" :loading="saving">Salvar</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  `,

  setup() {
    const userPermissions = inject('userPermissions')
    const members = ref([])
    const availableRoles = ref([])
    const availableUnits = ref([])
    const dialog = ref(false)
    const editingMember = ref(null)
    const avatarFile = ref(null)
    const uploading = ref(false)
    const saving = ref(false)

    const memberForm = reactive({
      name: '',
      role_id: '',
      unit_id: '',
      avatar: '',
      birth_date: '',
      is_active: true,
    })

    const rules = {
      required: (value) => !!value || 'Campo obrigatório.',
    }

    const getAvatarUrl = (avatar) => {
      if (!avatar) return 'https://i.pravatar.cc/100'
      if (avatar.startsWith('http')) return avatar
      return `/uploads/members/${avatar}`
    }

    const getPreviewAvatar = () => {
      if (avatarFile.value) {
        return URL.createObjectURL(avatarFile.value)
      }
      return memberForm.avatar ? getAvatarUrl(memberForm.avatar) : 'https://i.pravatar.cc/100'
    }

    const handleAvatarUpload = async (file) => {
      if (!file) return

      uploading.value = true
      try {
        const formData = new FormData()
        formData.append('avatar', file)
        formData.append('type', 'member')

        const response = await fetch('${API_BASE_URL}/upload.php', {
          method: 'POST',
          body: formData,
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            memberForm.avatar = result.filename
          }
        }
      } catch (error) {
        console.error('Erro ao fazer upload:', error)
      } finally {
        uploading.value = false
      }
    }

    const loadMembers = async () => {
      try {
        const response = await fetch('${API_BASE_URL}/members.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            members.value = result.data
          }
        }
      } catch (error) {
        console.error('Erro ao carregar membros:', error)
      }
    }

    const loadRolesAndUnits = async () => {
      try {
        // Carregar cargos
        const rolesResponse = await fetch('${API_BASE_URL}/roles.php')
        if (rolesResponse.ok) {
          const rolesResult = await rolesResponse.json()
          if (rolesResult.success) {
            availableRoles.value = rolesResult.data
          }
        }

        // Carregar unidades
        const unitsResponse = await fetch('${API_BASE_URL}/units.php')
        if (unitsResponse.ok) {
          const unitsResult = await unitsResponse.json()
          if (unitsResult.success) {
            availableUnits.value = unitsResult.data
          }
        }
      } catch (error) {
        console.error('Erro ao carregar dados:', error)
      }
    }

    const openCreateDialog = () => {
      editingMember.value = null
      avatarFile.value = null
      Object.assign(memberForm, {
        name: '',
        role_id: '',
        unit_id: '',
        avatar: '',
        birth_date: '',
        is_active: true,
      })
      dialog.value = true
    }

    const editMember = (member) => {
      editingMember.value = member
      avatarFile.value = null
      Object.assign(memberForm, {
        ...member,
        role_id: member.role_id || '',
        unit_id: member.unit_id || '',
      })
      dialog.value = true
    }

    const saveMember = async () => {
      if (!memberForm.name || !memberForm.role_id || !memberForm.unit_id) {
        return
      }

      saving.value = true
      try {
        const url = editingMember.value
          ? '${API_BASE_URL}/members.php?action=update'
          : '${API_BASE_URL}/members.php?action=create'
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(memberForm),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            await loadMembers()
            closeDialog()
          }
        }
      } catch (error) {
        console.error('Erro ao salvar membro:', error)
      } finally {
        saving.value = false
      }
    }

    const closeDialog = () => {
      dialog.value = false
      editingMember.value = null
      avatarFile.value = null
    }

    onMounted(() => {
      loadMembers()
      loadRolesAndUnits()
    })

    return {
      members,
      userPermissions,
      dialog,
      editingMember,
      memberForm,
      avatarFile,
      uploading,
      saving,
      availableRoles,
      availableUnits,
      rules,
      getAvatarUrl,
      getPreviewAvatar,
      handleAvatarUpload,
      openCreateDialog,
      editMember,
      saveMember,
      closeDialog,
    }
  },
}

const RegisterPontuateView = {
  template: `
    <v-container>
      <h2 class="text-h5 mb-4">⭐ Registrar Pontuação</h2>

      <v-card class="pa-6 rounded-lg" elevation="2">
        <v-form @submit.prevent="submitPontuation">
          <v-row>
            <v-col cols="12">
              <v-radio-group v-model="pontuationType" label="Tipo de Pontuação" inline>
                <v-radio label="Pontuação Coletiva" value="collective"></v-radio>
                <v-radio label="Pontuação Individual" value="individual"></v-radio>
              </v-radio-group>
            </v-col>

            <v-col cols="12" sm="6">
              <v-select
                v-model="selectedGame"
                label="Selecione a Brincadeira"
                :items="availableGames"
                item-title="name"
                item-value="id"
                prepend-inner-icon="mdi-gamepad"
                :rules="[rules.required]"
                @update:model-value="onGameChange"
              ></v-select>
            </v-col>

            <v-col cols="12" sm="6">
              <v-select
                v-model="selectedUnity"
                label="Selecione a Unidade"
                :items="availableUnits"
                item-title="name"
                item-value="id"
                prepend-inner-icon="mdi-office-building"
                :rules="[rules.required]"
              ></v-select>
            </v-col>

            <v-col cols="12" sm="6" v-if="pontuationType === 'individual'">
              <v-select
                v-model="selectedMember"
                label="Selecione o Membro"
                :items="filteredMembers"
                item-title="name"
                item-value="id"
                prepend-inner-icon="mdi-account"
                :rules="pontuationType === 'individual' ? [rules.required] : []"
              ></v-select>
            </v-col>

            <!-- Cronômetro para brincadeiras com timer -->
            <v-col cols="12" v-if="selectedGameData?.use_timer">
              <v-card class="pa-4 rounded-lg" color="orange-lighten-5">
                <div class="text-center">
                  <h3 class="text-h6 mb-2">⏱️ Cronômetro</h3>
                  <div class="text-h3 font-weight-bold mb-4">{{ formatTime(timer) }}</div>
                  <div class="d-flex justify-center gap-2">
                    <v-btn
                      @click="startTimer"
                      color="green"
                      :disabled="timerRunning"
                    >
                      <v-icon start>mdi-play</v-icon> Iniciar
                    </v-btn>
                    <v-btn
                      @click="stopTimer"
                      color="red"
                      :disabled="!timerRunning"
                    >
                      <v-icon start>mdi-stop</v-icon> Parar
                    </v-btn>
                    <v-btn
                      @click="resetTimer"
                      color="grey"
                    >
                      <v-icon start>mdi-refresh</v-icon> Resetar
                    </v-btn>
                  </div>
                  <p class="text-caption mt-2">Tempo será convertido em pontos automaticamente</p>
                </div>
              </v-card>
            </v-col>

            <!-- Pontuação manual para brincadeiras sem timer -->
            <v-col cols="12" v-else>
              <v-text-field
                v-model="pontuationValue"
                label="Pontuação"
                type="number"
                prepend-inner-icon="mdi-star"
                :rules="[rules.required, rules.positive]"
                :placeholder="selectedGameData ? 'Pontuação fixa: ' + selectedGameData.fixed_points : ''"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="pontuationDescription"
                label="Observações (Opcional)"
                prepend-inner-icon="mdi-text"
                rows="3"
                placeholder="Detalhes sobre a pontuação..."
              ></v-textarea>
            </v-col>

            <v-col cols="12">
              <v-btn
                type="submit"
                color="primary"
                size="large"
                :disabled="!isFormValid || (selectedGameData?.use_timer && !timerCompleted)"
              >
                {{ selectedGameData?.use_timer ? 'Registrar Tempo' : 'Registrar Pontuação' }}
              </v-btn>
            </v-col>
          </v-row>
        </v-form>
      </v-card>
    </v-container>
  `,

  setup() {
    // Variáveis reativas
    const pontuationType = ref('collective')
    const selectedGame = ref(null)
    const selectedUnity = ref(null)
    const selectedMember = ref(null)
    const pontuationValue = ref('')
    const pontuationDescription = ref('')

    // Cronômetro
    const timer = ref(0)
    const timerRunning = ref(false)
    const timerInterval = ref(null)

    // Dados
    const availableGames = ref([])
    const availableUnits = ref([])
    const members = ref([])

    // Computed
    const selectedGameData = computed(() => {
      return availableGames.value.find((g) => g.id == selectedGame.value) || null
    })

    const filteredMembers = computed(() => {
      if (!selectedUnity.value) return []
      return members.value.filter((member) => member.unit_id == selectedUnity.value)
    })

    const isFormValid = computed(() => {
      const baseValid = selectedGame.value && selectedUnity.value
      if (pontuationType.value === 'individual') {
        return baseValid && selectedMember.value
      }
      return baseValid
    })

    const timerCompleted = computed(() => {
      return timer.value > 0 && !timerRunning.value
    })

    // Regras de validação
    const rules = {
      required: (value) => !!value || 'Campo obrigatório.',
      positive: (value) => value > 0 || 'Pontuação deve ser positiva',
    }

    // Métodos do Cronômetro
    const startTimer = () => {
      if (timerRunning.value) return
      timerRunning.value = true
      timerInterval.value = setInterval(() => {
        timer.value += 1
      }, 1000)
    }

    const stopTimer = () => {
      timerRunning.value = false
      if (timerInterval.value) {
        clearInterval(timerInterval.value)
        timerInterval.value = null
      }
    }

    const resetTimer = () => {
      stopTimer()
      timer.value = 0
    }

    const formatTime = (seconds) => {
      const mins = Math.floor(seconds / 60)
      const secs = seconds % 60
      return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }

    // Quando o jogo muda, resetar o cronômetro se necessário
    const onGameChange = () => {
      resetTimer()
      if (!selectedGameData.value?.use_timer) {
        pontuationValue.value = selectedGameData.value?.fixed_points || ''
      }
    }

    // Carregar dados
    const loadData = async () => {
      try {
        console.log('Carregando dados...')

        const [gamesRes, unitsRes, membersRes] = await Promise.all([
          fetch('${API_BASE_URL}/games.php'),
          fetch('${API_BASE_URL}/units.php'),
          fetch('${API_BASE_URL}/members.php'),
        ])

        if (gamesRes.ok) {
          const gamesData = await gamesRes.json()
          availableGames.value = gamesData.success ? gamesData.data : []
          console.log('Jogos carregados:', availableGames.value.length)
        }

        if (unitsRes.ok) {
          const unitsData = await unitsRes.json()
          availableUnits.value = unitsData.success ? unitsData.data : []
          console.log('Unidades carregadas:', availableUnits.value.length)
        }

        if (membersRes.ok) {
          const membersData = await membersRes.json()
          members.value = membersData.success ? membersData.data : []
          console.log('Membros carregados:', members.value.length)
        }
      } catch (error) {
        console.error('Erro ao carregar dados:', error)
      }
    }

    // Submeter pontuação
    const submitPontuation = async () => {
      console.log('Enviando pontuação...')

      let points = 0

      if (selectedGameData.value?.use_timer) {
        // Para jogos com timer, usar pontuação baseada no tempo (quanto menor o tempo, mais pontos)
        const basePoints = selectedGameData.value.fixed_points || 100
        points = Math.max(10, basePoints - Math.floor(timer.value / 10))
        console.log(`Timer: ${timer.value}s, Pontos: ${points}`)
      } else {
        points = parseInt(pontuationValue.value) || selectedGameData.value?.fixed_points || 10
        console.log(`Pontuação manual: ${points}`)
      }

      const pontuationData = {
        type: pontuationType.value,
        game_id: selectedGame.value,
        unit_id: selectedUnity.value,
        member_id: pontuationType.value === 'individual' ? selectedMember.value : null,
        value: points,
        timer_value: selectedGameData.value?.use_timer ? timer.value : null,
        description: pontuationDescription.value,
      }

      console.log('Dados enviados:', pontuationData)

      try {
        const response = await fetch('${API_BASE_URL}/scores.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(pontuationData),
        })

        if (response.ok) {
          const result = await response.json()
          console.log('Resposta da API:', result)

          if (result.success) {
            // Reset form
            resetForm()
            alert('✅ Pontuação registrada com sucesso!')
          } else {
            alert('❌ Erro: ' + result.message)
          }
        } else {
          alert('❌ Erro na requisição: ' + response.status)
        }
      } catch (error) {
        console.error('Erro ao registrar pontuação:', error)
        alert('❌ Erro de conexão. Verifique o console.')
      }
    }

    const resetForm = () => {
      pontuationType.value = 'collective'
      selectedGame.value = null
      selectedUnity.value = null
      selectedMember.value = null
      pontuationValue.value = ''
      pontuationDescription.value = ''
      resetTimer()
    }

    // Lifecycle
    onMounted(() => {
      console.log('RegisterPontuateView montado')
      loadData()
    })

    onUnmounted(() => {
      console.log('RegisterPontuateView desmontado')
      if (timerInterval.value) {
        clearInterval(timerInterval.value)
      }
    })

    return {
      pontuationType,
      selectedGame,
      selectedUnity,
      selectedMember,
      pontuationValue,
      pontuationDescription,
      timer,
      timerRunning,
      availableGames,
      availableUnits,
      filteredMembers,
      selectedGameData,
      isFormValid,
      timerCompleted,
      rules,
      startTimer,
      stopTimer,
      resetTimer,
      formatTime,
      onGameChange,
      submitPontuation,
    }
  },
}

const RankingView = {
  template: `
    <v-container>
      <v-row class="mb-4">
        <v-col cols="12">
          <div class="d-flex align-center justify-space-between">
            <div>
              <h2 class="text-h5 mb-1">🏆 Ranking de Unidades</h2>
              <p class="text-body-2 text-grey">Atualizado em: {{ lastUpdate }}</p>
            </div>
            <v-chip color="primary" size="small">
              <div class="spinner-grow me-2" style="width: 0.8rem; height: 0.8rem;"></div>
              AO VIVO
            </v-chip>
          </div>
        </v-col>
      </v-row>

      <!-- Loading -->
      <v-row v-if="loading">
        <v-col cols="12" class="text-center">
          <div class="spinner-grow text-primary" style="width: 2rem; height: 2rem;"></div>
          <p class="mt-2">Carregando ranking...</p>
        </v-col>
      </v-row>

      <!-- Conteúdo do Ranking -->
      <v-row v-else>
        <v-col cols="12">
          <!-- Pódio Top 3 -->
          <v-card class="pa-4 mb-6 rounded-lg" elevation="2" v-if="top3.length > 0">
            <div style="height:320px; position:relative;">
              <!-- Avatares posicionados acima das barras - ORDEM: 2º ESQUERDA, 1º MEIO, 3º DIREITA -->
              <div class="d-flex justify-space-around align-end" style="position:absolute; top:20px; left:0; right:0; z-index:10; height:80px;">
                <!-- 2º Lugar - Esquerda -->
                <div class="d-flex flex-column align-center" v-if="top3[1]">
                  <v-avatar size="60" :color="top3[1].color || 'primary'" class="mb-2">
                    <v-img v-if="top3[1].avatar" :src="top3[1].avatar" alt="Avatar" />
                    <v-icon v-else icon="mdi-office-building" color="white" />
                  </v-avatar>
                  <div class="position-badge second-place">
                    2º
                  </div>
                </div>

                <!-- 1º Lugar - Centro -->
                <div class="d-flex flex-column align-center" v-if="top3[0]">
                  <v-avatar size="70" :color="top3[0].color || 'primary'" class="mb-2">
                    <v-img v-if="top3[0].avatar" :src="top3[0].avatar" alt="Avatar" />
                    <v-icon v-else icon="mdi-office-building" color="white" />
                  </v-avatar>
                  <div class="position-badge first-place">
                    1º
                  </div>
                </div>

                <!-- 3º Lugar - Direita -->
                <div class="d-flex flex-column align-center" v-if="top3[2]">
                  <v-avatar size="60" :color="top3[2].color || 'primary'" class="mb-2">
                    <v-img v-if="top3[2].avatar" :src="top3[2].avatar" alt="Avatar" />
                    <v-icon v-else icon="mdi-office-building" color="white" />
                  </v-avatar>
                  <div class="position-badge third-place">
                    3º
                  </div>
                </div>
              </div>

              <bar-chart
                :chart-data="top3ChartData"
                :chart-options="chartOptions"
              />
            </div>
          </v-card>

          <!-- Lista dos demais -->
          <v-card class="rounded-lg" elevation="2" v-if="otherUnits.length > 0">
            <v-list lines="one">
              <v-list-item
                v-for="unit in otherUnits"
                :key="unit.id"
                class="other-ranking-item"
              >
                <template #prepend>
                  <div class="position-number mr-4">
                    {{ unit.position }}
                  </div>
                  <v-avatar size="40" class="mr-3" :color="unit.color || 'primary'">
                    <v-img v-if="unit.avatar" :src="unit.avatar" alt="Avatar" />
                    <v-icon v-else icon="mdi-office-building" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title>{{ unit.name }}</v-list-item-title>
                <v-list-item-subtitle>{{ unit.total_points }} pontos</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card>

          <!-- Mensagem se não houver dados -->
          <v-card v-else class="text-center pa-6 rounded-lg" elevation="2">
            <v-icon size="64" color="grey" class="mb-3">mdi-trophy-off</v-icon>
            <h3 class="text-h6">Nenhum dado de ranking disponível</h3>
            <p class="text-grey">Registre pontuações para ver o ranking</p>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  `,
  components: { BarChart },
  setup() {
    const rankingData = ref([])
    const loading = ref(true)
    const lastUpdate = ref('')

    const loadRanking = async () => {
      try {
        loading.value = true
        const response = await fetch('${API_BASE_URL}/ranking.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            rankingData.value = result.data
            lastUpdate.value = new Date().toLocaleString('pt-BR')
          }
        }
      } catch (error) {
        console.error('Erro ao carregar ranking:', error)
      } finally {
        loading.value = false
      }
    }

    // Atualizar a cada 30 segundos
    onMounted(() => {
      loadRanking()
      setInterval(loadRanking, 30000)
    })

    const top3 = computed(() => {
      return rankingData.value.slice(0, 3)
    })

    const otherUnits = computed(() => {
      return rankingData.value.slice(3)
    })

    const top3ChartData = computed(() => {
      const t = top3.value

      // Reorganizar a ordem: [2º, 1º, 3º] para exibição no gráfico
      const displayOrder = t.length >= 3 ? [t[1], t[0], t[2]] : t

      const d = {
        labels: displayOrder.map((u) => (u ? u.name : '')),
        datasets: [
          {
            label: 'Pontos',
            data: displayOrder.map((u) => (u ? u.total_points : 0)),
            backgroundColor: ['#C0C0C0', '#FFD700', '#CD7F32'], // Prata, Ouro, Bronze
            borderColor: ['#A8A8A8', '#D4AF37', '#8C7853'],
            borderWidth: 2,
            borderRadius: 10,
          },
        ],
      }
      return d
    })

    const chartOptions = ref({
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: 100, bottom: 10 } },
      plugins: {
        legend: { display: false },
        tooltip: {
          enabled: true,
          callbacks: {
            label: (ctx) => `${ctx.parsed.y} pontos`,
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          // Removido o limite máximo fixo de 100
          ticks: {
            stepSize: 20,
            color: '#666',
            callback: function (value) {
              return value + ' pts'
            },
          },
          grid: { color: 'rgba(0,0,0,0.1)' },
        },
        x: {
          grid: { display: false },
          ticks: { color: '#333', font: { weight: 'bold' } },
        },
      },
    })

    return {
      rankingData,
      top3,
      otherUnits,
      top3ChartData,
      chartOptions,
      loading,
      lastUpdate,
    }
  },
}

const GamesView = {
  template: `
    <v-container>
      <v-row class="mb-4">
        <v-col cols="12">
          <h2 class="text-h5 mb-2">🎮 Brincadeiras</h2>
          <p class="text-body-2 mb-4">Cadastre as brincadeiras e configure se usam cronômetro</p>
        </v-col>
      </v-row>

      <!-- Lista de Brincadeiras -->
      <v-row>
        <v-col cols="12">
          <v-card class="rounded-lg" elevation="2">
            <v-list lines="two" v-if="games.length > 0">
              <v-list-item
                v-for="game in games"
                :key="game.id"
              >
                <template #prepend>
                  <v-avatar size="48" rounded="circle" :color="game.use_timer ? 'orange' : 'blue'">
                    <v-icon :icon="game.use_timer ? 'mdi-timer' : 'mdi-gamepad'" color="white" />
                  </v-avatar>
                </template>

                <v-list-item-title class="font-weight-medium">
                  {{ game.name }}
                  <v-chip size="x-small" :color="game.use_timer ? 'orange' : 'blue'" class="ml-1">
                    {{ game.use_timer ? 'Com Cronômetro' : 'Sem Cronômetro' }}
                  </v-chip>
                </v-list-item-title>

                <v-list-item-subtitle>
                  {{ game.description }}
                </v-list-item-subtitle>

                <template #append>
                  <v-btn
                    icon
                    variant="text"
                    @click="editGame(game)"
                  >
                    <v-icon icon="mdi-pencil" />
                  </v-btn>
                </template>
              </v-list-item>
            </v-list>

            <div v-else class="text-center pa-6">
              <v-icon size="64" color="grey" class="mb-3">mdi-gamepad-off</v-icon>
              <h3 class="text-h6">Nenhuma brincadeira cadastrada</h3>
              <p class="text-grey">Clique no botão + para adicionar a primeira brincadeira</p>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Botão Flutuante -->
      <v-btn
        color="primary"
        icon="mdi-plus"
        size="large"
        class="position-fixed"
        style="bottom: 24px; right: 24px;"
        @click="openCreateDialog()"
      ></v-btn>

      <!-- Modal de Criação/Edição -->
      <v-dialog v-model="dialog" max-width="500">
        <v-card>
          <v-card-title>{{ editingGame ? 'Editar' : 'Criar' }} brincadeira</v-card-title>
          <v-card-text>
            <v-text-field
              label="Nome da Brincadeira"
              v-model="gameForm.name"
              prepend-inner-icon="mdi-gamepad"
              :rules="[rules.required]"
            />
            <v-textarea
              label="Descrição"
              v-model="gameForm.description"
              prepend-inner-icon="mdi-text"
              rows="3"
              counter
              maxlength="200"
            />
            <v-switch
              v-model="gameForm.use_timer"
              label="Usa cronômetro?"
              color="orange"
              :messages="gameForm.use_timer ? 'Pontuação baseada no tempo' : 'Pontuação fixa'"
            />
            <v-text-field
              v-if="!gameForm.use_timer"
              label="Pontuação Fixa"
              v-model="gameForm.fixed_points"
              type="number"
              prepend-inner-icon="mdi-star"
              :rules="[rules.positive]"
            />
            <v-text-field
              label="Cor de Identificação"
              v-model="gameForm.color"
              type="color"
              prepend-inner-icon="mdi-palette"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDialog">Cancelar</v-btn>
            <v-btn color="primary" @click="saveGame">Salvar</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  `,

  setup() {
    const games = ref([])
    const dialog = ref(false)
    const editingGame = ref(null)

    const gameForm = reactive({
      name: '',
      description: '',
      use_timer: false,
      fixed_points: 10,
      color: '#FF9800',
    })

    const rules = {
      required: (value) => !!value || 'Campo obrigatório.',
      positive: (value) => value > 0 || 'Pontuação deve ser positiva',
    }

    const loadGames = async () => {
      try {
        const response = await fetch('${API_BASE_URL}/games.php')
        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            games.value = result.data
          }
        }
      } catch (error) {
        console.error('Erro ao carregar brincadeiras:', error)
      }
    }

    const openCreateDialog = () => {
      editingGame.value = null
      Object.assign(gameForm, {
        name: '',
        description: '',
        use_timer: false,
        fixed_points: 10,
        color: '#FF9800',
      })
      dialog.value = true
    }

    const editGame = (game) => {
      editingGame.value = game
      Object.assign(gameForm, { ...game })
      dialog.value = true
    }

    const saveGame = async () => {
      if (!gameForm.name) return

      try {
        const url = editingGame.value
          ? '${API_BASE_URL}/games.php?action=update'
          : '${API_BASE_URL}/games.php?action=create'
        const response = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(gameForm),
        })

        if (response.ok) {
          const result = await response.json()
          if (result.success) {
            await loadGames()
            closeDialog()
          }
        }
      } catch (error) {
        console.error('Erro ao salvar brincadeira:', error)
      }
    }

    const closeDialog = () => {
      dialog.value = false
      editingGame.value = null
    }

    onMounted(() => {
      loadGames()
    })

    return {
      games,
      dialog,
      editingGame,
      gameForm,
      rules,
      openCreateDialog,
      editGame,
      saveGame,
      closeDialog,
    }
  },
}

const CreditsView = {
  template: `
    <v-container class="credits-container">
      <v-row justify="center">
        <v-col cols="12" md="8" lg="6">
          <v-card class="pa-6 rounded-lg text-center" elevation="2" color="blue-lighten-5">
            <!-- Cabeçalho -->
            <div class="mb-6">
              <v-icon size="64" color="primary" class="mb-4">mdi-heart</v-icon>
              <h1 class="text-h4 font-weight-bold primary--text mb-2">Sistema Desbravadores</h1>
              <v-chip color="primary" variant="outlined" class="mb-4">
                Versão 1.0.0
              </v-chip>
              <p class="text-body-1 text-grey">
                Sistema de gerenciamento e pontuação para clubes de desbravadores
              </p>
            </div>

            <!-- Informações do Desenvolvedor -->
            <v-card class="pa-4 mb-4 rounded-lg" color="white">
              <v-avatar size="80" color="primary" class="mb-3">
                <v-icon size="40" color="white">mdi-account</v-icon>
              </v-avatar>
              <h3 class="text-h6 font-weight-bold mb-2">Desenvolvido com 💙 pelo</h3>
              <p class="text-body-1 mb-1">
                <strong>Conselheiro Associado da melhor Unidade</strong>
              </p>
              <p class="text-body-2 text-grey mb-3">
                "Homem de Efésios 5, breve investido em líder"
              </p>
              <v-divider class="my-3"></v-divider>
              <p class="text-caption text-grey">
                "Porque somos feitura sua, criados em Cristo Jesus para as boas obras,
                as quais Deus preparou para que andássemos nelas." - Efésios 2:10
              </p>
            </v-card>

            <!-- Tecnologias Utilizadas -->
            <v-card class="pa-4 rounded-lg" color="white">
              <h4 class="text-h6 font-weight-bold mb-4">🛠 Tecnologias Utilizadas</h4>
              <v-row>
                <v-col cols="6" sm="3" class="text-center">
                  <v-icon color="green" size="40" class="mb-2">mdi-vuejs</v-icon>
                  <p class="text-caption font-weight-bold">Vue.js</p>
                </v-col>
                <v-col cols="6" sm="3" class="text-center">
                  <v-icon color="blue" size="40" class="mb-2">mdi-language-php</v-icon>
                  <p class="text-caption font-weight-bold">PHP</p>
                </v-col>
                <v-col cols="6" sm="3" class="text-center">
                  <v-icon color="orange" size="40" class="mb-2">mdi-database</v-icon>
                  <p class="text-caption font-weight-bold">MySQL</p>
                </v-col>
                <v-col cols="6" sm="3" class="text-center">
                  <v-icon color="deep-purple" size="40" class="mb-2">mdi-material-design</v-icon>
                  <p class="text-caption font-weight-bold">Vuetify</p>
                </v-col>
              </v-row>
            </v-card>

            <!-- Footer -->
            <div class="mt-6">
              <p class="text-caption text-grey">
                © 2025 Sistema Desbravadores. Todos os direitos reservados.
              </p>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  `,
}
const currentView = computed(() => {
  switch (selected.value) {
    case 'account':
      return AccountView
    case 'users':
      return UsersView
    case 'unity':
      return UnityView
    case 'member':
      return MembersView
    case 'registerpontuate':
      return RegisterPontuateView
    case 'ranking':
      return RankingView
    case 'games':
      return GamesView
    case 'credits':
      return CreditsView
    default:
      return HomeView
  }
})
</script>

<style scoped>
.spinner-grow {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  vertical-align: -0.125em;
  background-color: currentColor;
  border-radius: 50%;
  opacity: 0;
  animation: 0.75s linear infinite spinner-grow;
}

@keyframes spinner-grow {
  0% {
    transform: scale(0);
  }
  50% {
    opacity: 1;
    transform: none;
  }
}

.avatar-edit-btn {
  position: absolute;
  bottom: 0;
  right: 0;
  background: white !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.avatar-edit-btn:hover {
  transform: scale(1.1);
}

/* Estilo para números de posição no ranking */
.position-number {
  width: 32px;
  height: 32px;
  background: #f0f0f0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 14px;
  color: #666;
}

.other-ranking-item {
  border-left: 3px solid transparent;
  transition: all 0.3s ease;
}

.other-ranking-item:hover {
  border-left-color: #2196f3;
  background: #f5f5f5;
}

/* Ajuste para avatares com botão de edição */
.v-avatar {
  position: relative;
}

.v-main {
  transition: all 0.3s ease;
  margin-left: auto;
}

.v-navigation-drawer--rail ~ .v-main {
  margin-left: 56px;
}

.v-navigation-drawer:not(.v-navigation-drawer--rail) ~ .v-main {
  margin-left: 256px;
}

.position-fixed {
  position: fixed;
}

.chart-avatars {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 100%;
  pointer-events: none;
}

.bar-avatar {
  position: absolute;
  bottom: 60%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  z-index: 10;
}

.avatar-container {
  position: relative;
  margin-bottom: 8px;
}

.avatar-glow {
  border: 3px solid;
  box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
  background: white;
}

.bar-avatar.position-0 .avatar-glow {
  border-color: #c0c0c0;
}

.bar-avatar.position-1 .avatar-glow {
  border-color: #ffd700;
  animation: gold-pulse 2s infinite;
}

.bar-avatar.position-2 .avatar-glow {
  border-color: #cd7f32;
}

.unit-info {
  text-align: center;
  background: rgba(255, 255, 255, 0.9);
  padding: 4px 8px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
}

.unit-name {
  font-weight: bold;
  font-size: 0.8em;
  color: #333;
  white-space: nowrap;
}

.unit-points {
  font-size: 0.7em;
  color: #666;
  font-weight: 500;
}

@media (max-width: 600px) {
  .v-main {
    padding: 4px !important;
  }
  .v-container {
    padding: 4px !important;
  }
  .v-card {
    margin: 4px 0 !important;
  }
}

/* Efeito de pulsação para o primeiro lugar */
@keyframes gold-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.7);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 0 0 10px rgba(255, 215, 0, 0);
    transform: scale(1.05);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(255, 215, 0, 0);
    transform: scale(1);
  }
}

/* Ponto pulsante */
.pulse-dot {
  position: absolute;
  top: -2px;
  right: -2px;
  width: 12px;
  height: 12px;
  background: #ff4444;
  border-radius: 50%;
  animation: live-pulse 1.5s infinite;
  border: 2px solid white;
}

@keyframes live-pulse {
  0% {
    transform: scale(0.8);
    opacity: 1;
  }
  50% {
    transform: scale(1.2);
    opacity: 0.7;
  }
  100% {
    transform: scale(0.8);
    opacity: 1;
  }
}
</style>
