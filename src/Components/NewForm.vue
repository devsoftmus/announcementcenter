<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="announcement__form">
		<input v-model="subject"
			class="announcement__form__subject"
			type="text"
			name="subject"
			minlength="1"
			maxlength="512"
			:placeholder="t('announcementcenter', 'New announcement subject')">

		<textarea v-model="message"
			class="announcement__form__message"
			name="message"
			rows="4"
			:placeholder="t('announcementcenter', 'Write announcement text, Markdown can be used …')" />

		<div class="announcement__form__image-upload">
			<label class="image-upload-label">
				<input ref="imageInput"
					type="file"
					accept="image/*"
					@change="onImageSelect"
					style="display: none;">
				<div class="image-upload-button">
					<IconImage :size="20" />
					<span>{{ t('announcementcenter', 'Add cover image') }}</span>
				</div>
			</label>


			<div v-if="coverPreview" class="image-preview">
				<img :src="coverPreview" :alt="t('announcementcenter', 'Cover image preview')" />
				<button type="button" class="remove-image" @click="removeImage">
					<IconClose :size="16" />
				</button>
			</div>
		</div>

		<div class="announcement__form__buttons">
			<NcButton type="primary"
				:disabled="!subject"
				@click="onAnnounce">
				{{ t('announcementcenter', 'Announce') }}
			</NcButton>

			<NcActions>
				<NcActionCheckbox value="1"
					:checked.sync="createActivities">
					{{ t('announcementcenter', 'Create activities') }}
				</NcActionCheckbox>
				<NcActionCheckbox value="1"
					:checked.sync="createNotifications">
					{{ t('announcementcenter', 'Create notifications') }}
				</NcActionCheckbox>
				<NcActionCheckbox value="1"
					:checked.sync="sendEmails">
					{{ t('announcementcenter', 'Send emails') }}
				</NcActionCheckbox>
				<NcActionCheckbox value="1"
					:checked.sync="allowComments">
					{{ t('announcementcenter', 'Allow comments') }}
				</NcActionCheckbox>
				<NcActionSeparator />
				<NcActionInput v-model="groups"
					icon="icon-group"
					type="multiselect"
					:options="groupOptions"
					track-by="id"
					:multiple="true"
					:input-label="t('announcementcenter', 'Visibility')"
					:placeholder="t('announcementcenter', 'Everyone')"
					:title="t('announcementcenter', 'These groups will be able to see the announcement. If no group is selected, all users can see it.')"
					@search="onSearchChanged">
					{{ t('announcementcenter', 'Everyone') }}
				</NcActionInput>
				<NcActionSeparator />
				<NcActionInput type="datetime-local"
					:label="t('announcementcenter', 'Schedule announcement time')"
					:disabled="!scheduleEnabled"
					is-native-picker
					hide-label
					:value="scheduleTime"
					:min="new Date()"
					@change="setScheduleTime">
					<template #icon>
						<IconClockStart :size="20" />
					</template>
				</NcActionInput>
				<NcActionSeparator />
				<NcActionInput type="datetime-local"
					:label="t('announcementcenter', 'Schedule deletion time')"
					:disabled="!deleteEnabled"
					is-native-picker
					hide-label
					:value="deleteTime"
					:min="getMinDeleteTime()"
					id-native-date-time-picker="date-time-picker-delete_id"
					@change="setDeleteTime">
					<template #icon>
						<IconClockEnd :size="20" />
					</template>
				</NcActionInput>
			</NcActions>
		</div>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import debounce from 'debounce'
import { loadState } from '@nextcloud/initial-state'
import {
	postAnnouncement,
	searchGroups,
} from '../services/announcementsService.js'
import { showError } from '@nextcloud/dialogs'
import { remark } from 'remark'
import strip from 'strip-markdown'
import IconClockStart from 'vue-material-design-icons/ClockStart.vue'
import IconClockEnd from 'vue-material-design-icons/ClockEnd.vue'
import IconImage from 'vue-material-design-icons/Image.vue'
import IconClose from 'vue-material-design-icons/Close.vue'

export default {
	name: 'NewForm',

	components: {
		IconClockEnd,
		IconClockStart,
		IconImage,
		IconClose,
		NcActions,
		NcActionCheckbox,
		NcActionInput,
		NcActionSeparator,
		NcButton,
	},

	data() {
		return {
			subject: '',
			message: '',
			createActivities: loadState('announcementcenter', 'createActivities'),
			createNotifications: loadState('announcementcenter', 'createNotifications'),
			sendEmails: loadState('announcementcenter', 'sendEmails'),
			allowComments: loadState('announcementcenter', 'allowComments'),
			groups: [],
			groupOptions: [],
			scheduleEnabled: true,
			deleteEnabled: true,
			scheduleTime: null,
			deleteTime: null,
			// Поля для изображения
			coverPath: null,
			coverPreview: null,
			coverFile: null,
		}
	},

	mounted() {
		this.searchGroups('')
	},

	methods: {
		resetForm() {
			this.subject = ''
			this.message = ''
			this.createActivities = loadState('announcementcenter', 'createActivities')
			this.createNotifications = loadState('announcementcenter', 'createNotifications')
			this.sendEmails = loadState('announcementcenter', 'sendEmails')
			this.allowComments = loadState('announcementcenter', 'allowComments')
			this.groups = []
			this.scheduleEnabled = true
			this.deleteEnabled = true
			this.scheduleTime = null
			this.deleteTime = null
			// Сброс полей изображения
			this.coverPath = null
			this.coverPreview = null
			this.coverFile = null
		},

		onSearchChanged: debounce(function(search) {
			this.searchGroups(search)
		}, 300),

		setScheduleTime(event) {
			this.scheduleTime = new Date(event.target.value)
			if (this.deleteTime && this.scheduleTime > this.deleteTime) {
				this.deleteTime = this.scheduleTime
			}
		},

		setDeleteTime(event) {
			this.deleteTime = new Date(event.target.value)
			if (this.scheduleTime && this.scheduleTime > this.deleteTime) {
				this.deleteTime = this.scheduleTime
			}
		},

		getMinDeleteTime() {
			if (this.scheduleTime) {
				return this.scheduleTime
			}
			return new Date()
		},

		// Методы для работы с изображением
		onImageSelect(event) {
			const file = event.target.files[0]
			if (!file) return

			// Проверяем тип файла
			if (!file.type.startsWith('image/')) {
				showError(t('announcementcenter', 'Please select a valid image file'))
				return
			}

			// Проверяем размер файла (максимум 5MB)
			if (file.size > 5 * 1024 * 1024) {
				showError(t('announcementcenter', 'Image file is too large. Maximum size is 5MB'))
				return
			}

			this.coverFile = file

			// Создаем предварительный просмотр
			const reader = new FileReader()
			reader.onload = (e) => {
				this.coverPreview = e.target.result
			}
			reader.readAsDataURL(file)
		},

		removeImage() {
			this.coverFile = null
			this.coverPreview = null
			this.coverPath = null
			// Очищаем input
			if (this.$refs.imageInput) {
				this.$refs.imageInput.value = ''
			}
		},

		async searchGroups(search) {
			const response = await searchGroups(search)
			this.groupOptions = response.data.ocs.data
		},

		async onAnnounce() {
			const groups = this.groups.map(group => {
				return group.id
			})

			const plainMessage = await remark()
				.use(strip, {
					keep: ['blockquote', 'link', 'listItem'],
				})
				.process(this.message)

			try {
				const response = await postAnnouncement(
					this.subject,
					this.message,
					plainMessage.value,
					groups,
					this.createActivities,
					this.createNotifications,
					this.sendEmails,
					this.allowComments,
					new Date(this.scheduleTime).getTime() / 1000, // time in seconds
					new Date(this.deleteTime).getTime() / 1000,
					this.coverFile // передаем файл напрямую
				)
				this.$store.dispatch('addAnnouncement', response.data.ocs.data)

				this.resetForm()
			} catch (e) {
				console.error(e)
				showError(t('announcementcenter', 'An error occurred while posting the announcement'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.announcement__form {
	max-width: 690px;
	padding: 0 10px;
	margin: 70px auto 35px;
	font-size: 15px;

	&__subject {
		width: 100%;
		font-weight: bold;
	}

	&__message {
		width: 100%;
		resize: vertical;
	}

	&__buttons {
		display: flex;
		justify-content: right;

		:deep(.button-vue) {
			margin-right: 10px;
		}
	}

	&__timepicker {
		width: 100%;
	}

	&__image-upload {
		margin: 16px 0;
		border: 2px dashed var(--color-border);
		border-radius: var(--border-radius);
		padding: 16px;
		text-align: center;
		transition: border-color 0.2s ease;

		&:hover {
			border-color: var(--color-primary);
		}

		.image-upload-label {
			cursor: pointer;
			display: block;
		}

		.image-upload-button {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			padding: 12px;
			color: var(--color-text-maxcontrast);
			transition: color 0.2s ease;

			&:hover {
				color: var(--color-primary);
			}
		}

		.image-preview {
			position: relative;
			margin-top: 16px;
			display: inline-block;

			img {
				max-width: 200px;
				max-height: 150px;
				border-radius: var(--border-radius);
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
			}

			.remove-image {
				position: absolute;
				top: -8px;
				right: -8px;
				background: var(--color-error);
				color: var(--color-primary-text);
				border: none;
				border-radius: 50%;
				width: 24px;
				height: 24px;
				display: flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
				transition: background-color 0.2s ease;

				&:hover {
					background: var(--color-error-hover);
				}
			}
		}
	}
}
</style>
